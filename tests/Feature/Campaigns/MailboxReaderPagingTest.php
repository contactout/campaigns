<?php

use App\Contracts\Mail\GmailApi;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Exceptions\Mail\MailerHttpException;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Services\Mail\GmailMailboxReader;
use App\Services\Mail\OutlookMailboxReader;
use App\Services\OAuth\OAuthTokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Create an OAuth mailer connection with a still-valid access token.
 */
function mrpOAuthConnection(MailerType $type): MailerConnection
{
    return MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => $type,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@example.com',
            'from_email' => 'ada@example.com',
        ],
    ]);
}

/**
 * Build a fake Gmail API serving the given pages of message ids.
 *
 * @param  array<int, array<int, string>>  $pages
 */
function mrpGmailApi(array $pages, ?string $failingId = null, bool $failList = false): GmailApi
{
    return new class($pages, $failingId, $failList) implements GmailApi
    {
        /**
         * @var array<int, string|null>
         */
        public array $pageTokens = [];

        /**
         * @param  array<int, array<int, string>>  $pages
         */
        public function __construct(private array $pages, private ?string $failingId, private bool $failList) {}

        public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
        {
            return ['id' => 'msg-1', 'threadId' => 'thread-1'];
        }

        public function getProfileEmail(string $accessToken): string
        {
            return 'ada@example.com';
        }

        public function listMessageIds(string $accessToken, string $query, ?string $pageToken = null, int $maxResults = 100): array
        {
            if ($this->failList) {
                throw new RuntimeException('Gmail message list failed: timeout');
            }

            $this->pageTokens[] = $pageToken;
            $index = $pageToken === null ? 0 : (int) $pageToken;

            return [
                'ids' => $this->pages[$index],
                'nextPageToken' => isset($this->pages[$index + 1]) ? (string) ($index + 1) : null,
            ];
        }

        public function getMessage(string $accessToken, string $messageId): array
        {
            if ($messageId === $this->failingId) {
                throw new RuntimeException('Gmail message fetch failed: 503');
            }

            return [
                'messageId' => "<{$messageId}@mail.gmail.com>",
                'inReplyTo' => null,
                'references' => [],
                'fromEmail' => 'someone@example.com',
                'subject' => '',
                'text' => null,
            ];
        }
    };
}

test('gmail mailbox reader reads every page of results', function () {
    $api = mrpGmailApi([['a', 'b'], ['c'], ['d']]);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);

    $messages = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::now()->subHour());

    expect(array_map(fn ($message) => $message->messageId, $messages))->toBe([
        '<a@mail.gmail.com>',
        '<b@mail.gmail.com>',
        '<c@mail.gmail.com>',
        '<d@mail.gmail.com>',
    ])->and($api->pageTokens)->toBe([null, '1', '2']);
});

test('gmail mailbox reader throws instead of returning an empty mailbox when listing fails', function () {
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), mrpGmailApi([], failList: true));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::now()->subHour()))
        ->toThrow(RuntimeException::class, 'Gmail message list failed');
});

test('gmail mailbox reader throws instead of skipping a message it could not fetch', function () {
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), mrpGmailApi([['a', 'b', 'c']], failingId: 'b'));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::now()->subHour()))
        ->toThrow(RuntimeException::class, 'Gmail message fetch failed');
});

test('outlook mailbox reader follows every next link oldest first', function () {
    $next = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skiptoken=page2';
    $requests = [];

    Http::fake(function (Request $request) use ($next, &$requests) {
        $requests[] = $request->url();

        if (str_contains($request->url(), 'skiptoken=page2')) {
            return Http::response(['value' => [['internetMessageId' => '<two@outlook.com>']]]);
        }

        return Http::response([
            'value' => [['internetMessageId' => '<one@outlook.com>']],
            '@odata.nextLink' => $next,
        ]);
    });

    $since = CarbonImmutable::parse('2026-03-01 09:00:00');
    $messages = (new OutlookMailboxReader(app(OAuthTokenManager::class)))
        ->fetch(mrpOAuthConnection(MailerType::Outlook), $since);

    expect(array_map(fn ($message) => $message->messageId, $messages))->toBe(['<one@outlook.com>', '<two@outlook.com>'])
        ->and($requests)->toHaveCount(2)
        ->and(urldecode($requests[0]))->toContain('$orderby=receivedDateTime asc')
        ->and(urldecode($requests[0]))->toContain('$filter=receivedDateTime ge '.$since->toIso8601String())
        ->and(urldecode($requests[1]))->toBe($next);
});

test('outlook mailbox reader throws instead of returning an empty mailbox when graph fails', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ServiceUnavailable']], 503),
    ]);

    $reader = new OutlookMailboxReader(app(OAuthTokenManager::class));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Outlook), CarbonImmutable::now()->subHour()))
        ->toThrow(MailerHttpException::class, 'Outlook mailbox read failed.');
});

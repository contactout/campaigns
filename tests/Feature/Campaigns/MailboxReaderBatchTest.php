<?php

use App\Contracts\Mail\GmailApi;
use App\Data\MailboxBatch;
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
 * Build a fake Gmail API serving the given pages of message ids, newest first.
 *
 * @param  array<int, array<int, string>>  $pages
 * @param  array<int, string>  $vanishedIds  Ids deleted after they were listed.
 * @param  array<string, string>  $receivedAt  Received time per id; 07:00:00 when not given.
 */
function mrpGmailApi(array $pages, ?string $failingId = null, bool $failList = false, array $vanishedIds = [], array $receivedAt = []): GmailApi
{
    return new class($pages, $failingId, $failList, $vanishedIds, $receivedAt) implements GmailApi
    {
        /**
         * @var array<int, string|null>
         */
        public array $pageTokens = [];

        /**
         * @var array<int, string>
         */
        public array $fetchedIds = [];

        /**
         * @var array<int, string>
         */
        public array $queries = [];

        /**
         * @param  array<int, array<int, string>>  $pages
         * @param  array<int, string>  $vanishedIds
         * @param  array<string, string>  $receivedAt
         */
        public function __construct(private array $pages, private ?string $failingId, private bool $failList, private array $vanishedIds, private array $receivedAt) {}

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
            $this->queries[] = $query;
            $index = $pageToken === null ? 0 : (int) $pageToken;

            return [
                'ids' => $this->pages[$index],
                'nextPageToken' => isset($this->pages[$index + 1]) ? (string) ($index + 1) : null,
            ];
        }

        public function getMessage(string $accessToken, string $messageId): ?array
        {
            $this->fetchedIds[] = $messageId;

            if ($messageId === $this->failingId) {
                throw new RuntimeException('Gmail message fetch failed: 503');
            }

            if (in_array($messageId, $this->vanishedIds, true)) {
                return null;
            }

            return [
                'messageId' => "<{$messageId}@mail.gmail.com>",
                'inReplyTo' => null,
                'references' => [],
                'fromEmail' => 'someone@example.com',
                'subject' => '',
                'text' => null,
                'receivedAt' => CarbonImmutable::parse($this->receivedAt[$messageId] ?? '2026-03-01 07:00:00'),
            ];
        }
    };
}

/**
 * Get the message ids of a batch, in order.
 *
 * @return array<int, string>
 */
function mrpIds(MailboxBatch $batch): array
{
    return array_map(fn ($message) => $message->messageId, $batch->messages);
}

test('gmail mailbox reader lists every page and fetches all messages when they fit the limit', function () {
    $api = mrpGmailApi([['d', 'c'], ['b'], ['a']]);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 10);

    expect(mrpIds($batch))->toBe([
        '<a@mail.gmail.com>',
        '<b@mail.gmail.com>',
        '<c@mail.gmail.com>',
        '<d@mail.gmail.com>',
    ])->and($batch->hasMore)->toBeFalse()
        ->and($batch->messages[0]->receivedAt?->toDateTimeString())->toBe('2026-03-01 07:00:00')
        ->and($api->pageTokens)->toBe([null, '1', '2']);
});

test('gmail mailbox reader fetches only the oldest messages up to the limit', function () {
    $api = mrpGmailApi([['e', 'd', 'c'], ['b', 'a']]);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 2);

    expect(mrpIds($batch))->toBe(['<a@mail.gmail.com>', '<b@mail.gmail.com>'])
        ->and($batch->hasMore)->toBeTrue()
        ->and($api->fetchedIds)->toBe(['a', 'b']);
});

test('gmail mailbox reader skips a message deleted after it was listed', function () {
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), mrpGmailApi([['c', 'b', 'a']], vanishedIds: ['b']));

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 10);

    expect(mrpIds($batch))->toBe(['<a@mail.gmail.com>', '<c@mail.gmail.com>'])
        ->and($batch->hasMore)->toBeFalse();
});

test('gmail mailbox reader widens its search to whole seconds and drops what falls outside the window', function () {
    $api = mrpGmailApi([['after', 'in', 'early']], receivedAt: [
        'early' => '2026-03-01 06:59:59.500',
        'in' => '2026-03-01 07:00:00.250',
        'after' => '2026-03-01 07:00:01',
    ]);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);
    $since = CarbonImmutable::parse('2026-03-01 07:00:00');

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), $since, 10, $since->addSecond());

    expect($api->queries)->toBe([
        'after:'.($since->getTimestamp() - 1).' -from:me before:'.($since->getTimestamp() + 1),
    ])->and(mrpIds($batch))->toBe(['<in@mail.gmail.com>'])
        ->and($batch->messages[0]->mailboxId)->toBe('in')
        ->and($batch->hasMore)->toBeFalse();
});

test('gmail mailbox reader does not let mail from before the cursor fill the batch', function () {
    $early = array_map(fn (int $i) => "early-{$i}", range(1, 150));
    $receivedAt = array_fill_keys($early, '2026-03-01 06:59:59.500') + [
        'x' => '2026-03-01 07:00:10',
        'y' => '2026-03-01 07:00:20',
    ];

    $api = mrpGmailApi([['y', 'x', ...array_reverse($early)]], receivedAt: $receivedAt);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 07:00:00'), 100);

    expect(mrpIds($batch))->toBe(['<x@mail.gmail.com>', '<y@mail.gmail.com>'])
        ->and($batch->hasMore)->toBeFalse();
});

test('gmail mailbox reader skips already processed ids without fetching them', function () {
    $api = mrpGmailApi([['c', 'b', 'a']]);
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), $api);

    $batch = $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 1, skip: ['a']);

    expect(mrpIds($batch))->toBe(['<b@mail.gmail.com>'])
        ->and($batch->hasMore)->toBeTrue()
        ->and($api->fetchedIds)->toBe(['b']);
});

test('gmail mailbox reader throws instead of returning an empty mailbox when listing fails', function () {
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), mrpGmailApi([], failList: true));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 10))
        ->toThrow(RuntimeException::class, 'Gmail message list failed');
});

test('gmail mailbox reader throws instead of skipping a message it could not fetch', function () {
    $reader = new GmailMailboxReader(app(OAuthTokenManager::class), mrpGmailApi([['c', 'b', 'a']], failingId: 'b'));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Gmail), CarbonImmutable::parse('2026-03-01 06:00:00'), 10))
        ->toThrow(RuntimeException::class, 'Gmail message fetch failed');
});

/**
 * Fake Graph serving two pages of inbox messages, oldest first.
 *
 * @param  array<int, string>  $requests
 */
function mrpFakeGraphPages(array &$requests): string
{
    $next = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skiptoken=page2';

    Http::fake(function (Request $request) use (&$requests) {
        $requests[] = $request->url();

        if (str_contains($request->url(), 'skiptoken=page2')) {
            return Http::response(['value' => [
                ['internetMessageId' => '<three@outlook.com>', 'receivedDateTime' => '2026-03-01T07:02:00Z'],
            ]]);
        }

        return Http::response([
            'value' => [
                ['internetMessageId' => '<one@outlook.com>', 'receivedDateTime' => '2026-03-01T07:00:00Z'],
                ['internetMessageId' => '<two@outlook.com>', 'receivedDateTime' => '2026-03-01T07:01:00Z'],
            ],
            '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skiptoken=page2',
        ]);
    });

    return $next;
}

test('outlook mailbox reader follows next links oldest first when the messages fit the limit', function () {
    $requests = [];
    $next = mrpFakeGraphPages($requests);

    $since = CarbonImmutable::parse('2026-03-01 06:00:00');
    $batch = (new OutlookMailboxReader(app(OAuthTokenManager::class)))
        ->fetch(mrpOAuthConnection(MailerType::Outlook), $since, 10);

    expect(mrpIds($batch))->toBe(['<one@outlook.com>', '<two@outlook.com>', '<three@outlook.com>'])
        ->and($batch->hasMore)->toBeFalse()
        ->and($batch->messages[2]->receivedAt?->toDateTimeString())->toBe('2026-03-01 07:02:00')
        ->and($requests)->toHaveCount(2)
        ->and(urldecode($requests[0]))->toContain('$orderby=receivedDateTime asc')
        ->and(urldecode($requests[0]))->toContain('$top=10')
        ->and(urldecode($requests[0]))->toContain('$filter=receivedDateTime ge '.$since->toIso8601String())
        ->and(urldecode($requests[1]))->toBe($next);
});

test('outlook mailbox reader stops at the limit and reports that more mail is waiting', function () {
    $requests = [];
    mrpFakeGraphPages($requests);

    $batch = (new OutlookMailboxReader(app(OAuthTokenManager::class)))
        ->fetch(mrpOAuthConnection(MailerType::Outlook), CarbonImmutable::parse('2026-03-01 06:00:00'), 2);

    expect(mrpIds($batch))->toBe(['<one@outlook.com>', '<two@outlook.com>'])
        ->and($batch->hasMore)->toBeTrue()
        ->and($requests)->toHaveCount(1);
});

test('outlook mailbox reader reads a bounded window and skips ids already processed while paging', function () {
    $requests = [];
    mrpFakeGraphPages($requests);

    $since = CarbonImmutable::parse('2026-03-01 07:00:00');
    $batch = (new OutlookMailboxReader(app(OAuthTokenManager::class)))
        ->fetch(mrpOAuthConnection(MailerType::Outlook), $since, 2, $since->addSecond(), ['<one@outlook.com>']);

    expect(mrpIds($batch))->toBe(['<two@outlook.com>', '<three@outlook.com>'])
        ->and($batch->hasMore)->toBeFalse()
        ->and($requests)->toHaveCount(2)
        ->and(urldecode($requests[0]))->toContain(
            '$filter=receivedDateTime ge '.$since->toIso8601String().' and receivedDateTime lt '.$since->addSecond()->toIso8601String(),
        );
});

test('outlook mailbox reader throws instead of returning an empty mailbox when graph fails', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ServiceUnavailable']], 503),
    ]);

    $reader = new OutlookMailboxReader(app(OAuthTokenManager::class));

    expect(fn () => $reader->fetch(mrpOAuthConnection(MailerType::Outlook), CarbonImmutable::parse('2026-03-01 06:00:00'), 10))
        ->toThrow(MailerHttpException::class, 'Outlook mailbox read failed.');
});

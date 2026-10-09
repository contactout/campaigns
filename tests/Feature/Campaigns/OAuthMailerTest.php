<?php

use App\Actions\MailerConnections\VerifyMailerConnection;
use App\Contracts\Mail\GmailApi;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Enums\SendFailureType;
use App\Exceptions\Mail\MailerHttpException;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Services\Mail\GmailCampaignMailer;
use App\Services\Mail\OutlookCampaignMailer;
use App\Services\Mail\SendFailureClassifier;
use App\Services\OAuth\OAuthTokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fake Gmail API used by verification tests.
 */
class FakeGmailApi implements GmailApi
{
    public string $profileEmail = 'user@gmail.com';

    /**
     * @var array{id: string, threadId: string}
     */
    public array $sendResult = ['id' => 'msg-1', 'threadId' => 'thread-1'];

    public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
    {
        return $this->sendResult;
    }

    public function getProfileEmail(string $accessToken): string
    {
        return $this->profileEmail;
    }

    public function listMessageIds(string $accessToken, string $query, int $maxResults = 50): array
    {
        return [];
    }

    public function getMessage(string $accessToken, string $messageId): array
    {
        return [
            'messageId' => $messageId,
            'inReplyTo' => null,
            'references' => [],
            'fromEmail' => null,
            'subject' => '',
            'text' => null,
        ];
    }
}

test('outlook campaign mailer creates sends and returns message ids', function () {
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'mailer_type' => MailerType::Outlook,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@outlook.com',
            'from_email' => 'ada@outlook.com',
            'from_name' => 'Ada',
            'name' => 'Ada',
            'provider_user_id' => 'ms-1',
            'scope' => 'Mail.Send',
        ],
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if ($request->method() === 'POST' && $url === 'https://graph.microsoft.com/v1.0/me/messages') {
            return Http::response(['id' => 'graph-msg-1'], 201);
        }

        if ($request->method() === 'POST' && $url === 'https://graph.microsoft.com/v1.0/me/messages/graph-msg-1/send') {
            return Http::response(null, 202);
        }

        if ($request->method() === 'GET' && str_starts_with($url, 'https://graph.microsoft.com/v1.0/me/messages/graph-msg-1')) {
            return Http::response([
                'internetMessageId' => '<outlook-1@example.com>',
                'conversationId' => 'conv-1',
            ], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $result = app(OutlookCampaignMailer::class)->send(
        $connection,
        'recipient@example.com',
        'Hello',
        '<p>Hi</p>',
        ['List-Unsubscribe' => '<https://example.com/u>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
    );

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.microsoft.com/v1.0/me/messages'
        && $request['internetMessageHeaders'] === [
            ['name' => 'List-Unsubscribe', 'value' => '<https://example.com/u>'],
            ['name' => 'List-Unsubscribe-Post', 'value' => 'List-Unsubscribe=One-Click'],
        ]);

    expect($result)->toBeInstanceOf(SendResult::class)
        ->and($result->messageId)->toBe('<outlook-1@example.com>')
        ->and($result->threadId)->toBe('conv-1');

    Http::assertSentCount(3);
});

test('gmail campaign mailer puts unsubscribe headers in the raw message', function () {
    $connection = MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Gmail,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'g-access',
            'refresh_token' => 'g-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@gmail.com',
            'from_email' => 'ada@gmail.com',
            'from_name' => 'Ada',
        ],
    ]);

    $api = new class extends FakeGmailApi
    {
        public string $raw = '';

        public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
        {
            $this->raw = $raw;

            return parent::sendRaw($accessToken, $raw, $threadId);
        }
    };

    (new GmailCampaignMailer(app(OAuthTokenManager::class), $api))->send(
        $connection,
        'to@example.com',
        'Hello',
        '<p>Hi</p>',
        [
            'List-Unsubscribe' => '<https://example.com/u>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ],
    );

    $mime = (string) base64_decode(strtr($api->raw, '-_', '+/'));

    expect($mime)->toContain('List-Unsubscribe: <https://example.com/u>')
        ->and($mime)->toContain('List-Unsubscribe-Post: List-Unsubscribe=One-Click');
});

test('verifying a gmail connection marks it active', function () {
    $this->app->instance(GmailApi::class, new FakeGmailApi);

    $connection = MailerConnection::factory()->create([
        'mailer_type' => MailerType::Gmail,
        'status' => MailerConnectionStatus::Pending,
        'smtp_setting' => [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'user@gmail.com',
            'from_email' => 'user@gmail.com',
        ],
    ]);

    app(VerifyMailerConnection::class)->handle($connection);

    expect($connection->fresh()->status)->toBe(MailerConnectionStatus::Active)
        ->and($connection->fresh()->exception_type)->toBeNull();
});

test('verifying an outlook connection marks it active', function () {
    $connection = MailerConnection::factory()->create([
        'mailer_type' => MailerType::Outlook,
        'status' => MailerConnectionStatus::Pending,
        'smtp_setting' => [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'user@outlook.com',
            'from_email' => 'user@outlook.com',
        ],
    ]);

    Http::fake([
        'graph.microsoft.com/v1.0/me' => Http::response([
            'id' => 'ms-1',
            'mail' => 'user@outlook.com',
            'displayName' => 'User',
        ]),
    ]);

    app(VerifyMailerConnection::class)->handle($connection);

    expect($connection->fresh()->status)->toBe(MailerConnectionStatus::Active);
});

test('oauth token manager refreshes tokens that expire within 60 seconds', function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
    ]);

    $connection = MailerConnection::factory()->create([
        'mailer_type' => MailerType::Gmail,
        'smtp_setting' => [
            'access_token' => 'old-access',
            'refresh_token' => 'refresh-token',
            'expires_at' => CarbonImmutable::now()->addSeconds(30)->toIso8601String(),
            'email' => 'user@gmail.com',
        ],
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access',
            'expires_in' => 3600,
            'scope' => 'email',
        ]),
    ]);

    $token = app(OAuthTokenManager::class)->accessToken($connection);

    expect($token)->toBe('new-access')
        ->and($connection->fresh()->smtp_setting['access_token'])->toBe('new-access')
        ->and($connection->fresh()->smtp_setting['refresh_token'])->toBe('refresh-token');
});

test('gmail campaign mailer files a follow-up into the previous conversation', function () {
    $connection = MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Gmail,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'g-access',
            'refresh_token' => 'g-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@gmail.com',
            'from_email' => 'ada@gmail.com',
            'from_name' => 'Ada',
        ],
    ]);

    $api = new class extends FakeGmailApi
    {
        public string $raw = '';

        public ?string $threadId = null;

        public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
        {
            $this->raw = $raw;
            $this->threadId = $threadId;

            return parent::sendRaw($accessToken, $raw, $threadId);
        }
    };

    $result = (new GmailCampaignMailer(app(OAuthTokenManager::class), $api))->send(
        $connection,
        'to@example.com',
        'Hello',
        '<p>Hi</p>',
        [],
        new EmailThread(messageId: '<first@example.com>', threadId: 'gmail-thread-1'),
    );

    expect($api->threadId)->toBe('gmail-thread-1');

    $mime = (string) base64_decode(strtr($api->raw, '-_', '+/'));

    expect($mime)->toContain('In-Reply-To: <first@example.com>')
        ->and($mime)->toContain('References: <first@example.com>')
        ->and($result->messageId)->toStartWith('<')
        ->and($result->messageId)->toEndWith('>')
        ->and($result->threadId)->toBe('thread-1');
});

test('gmail campaign mailer starts a new conversation without a thread context', function () {
    $connection = MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Gmail,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'g-access',
            'refresh_token' => 'g-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@gmail.com',
            'from_email' => 'ada@gmail.com',
        ],
    ]);

    $api = new class extends FakeGmailApi
    {
        public ?string $threadId = 'set';

        public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
        {
            $this->threadId = $threadId;

            return parent::sendRaw($accessToken, $raw, $threadId);
        }
    };

    (new GmailCampaignMailer(app(OAuthTokenManager::class), $api))->send(
        $connection,
        'to@example.com',
        'Hello',
        '<p>Hi</p>',
    );

    expect($api->threadId)->toBeNull();
});

test('outlook campaign mailer creates a reply in the previous conversation', function () {
    $connection = MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Outlook,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@outlook.com',
            'from_email' => 'ada@outlook.com',
            'from_name' => 'Ada',
        ],
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if ($request->method() === 'POST' && $url === 'https://graph.microsoft.com/v1.0/me/messages/reply-msg-1/createReply') {
            return Http::response(['id' => 'graph-reply-1', 'conversationId' => 'conv-1'], 201);
        }

        if ($request->method() === 'PATCH' && $url === 'https://graph.microsoft.com/v1.0/me/messages/graph-reply-1') {
            return Http::response(['id' => 'graph-reply-1'], 200);
        }

        if ($request->method() === 'POST' && $url === 'https://graph.microsoft.com/v1.0/me/messages/graph-reply-1/send') {
            return Http::response(null, 202);
        }

        if ($request->method() === 'GET' && str_starts_with($url, 'https://graph.microsoft.com/v1.0/me/messages/graph-reply-1')) {
            return Http::response([
                'internetMessageId' => '<outlook-reply@example.com>',
                'conversationId' => 'conv-1',
            ], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $result = app(OutlookCampaignMailer::class)->send(
        $connection,
        'recipient@example.com',
        'Hello',
        '<p>Hi</p>',
        ['List-Unsubscribe' => '<https://example.com/u>'],
        new EmailThread(messageId: '<outlook-1@example.com>', threadId: 'conv-1', replyToId: 'reply-msg-1'),
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://graph.microsoft.com/v1.0/me/messages/reply-msg-1/createReply');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://graph.microsoft.com/v1.0/me/messages/graph-reply-1'
        && $request['body']['content'] === '<p>Hi</p>');

    expect($result->messageId)->toBe('<outlook-reply@example.com>')
        ->and($result->threadId)->toBe('conv-1')
        ->and($result->replyToId)->toBe('graph-reply-1');

    Http::assertSentCount(4);
});

test('outlook campaign mailer reports the graph status and error code when a send is rejected', function () {
    $connection = MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Outlook,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'email' => 'ada@outlook.com',
            'from_email' => 'ada@outlook.com',
        ],
    ]);

    Http::fake([
        'graph.microsoft.com/v1.0/me/messages' => Http::response([
            'error' => ['code' => 'ErrorInvalidRecipients', 'message' => 'At least one recipient is not valid.'],
        ], 400),
    ]);

    try {
        app(OutlookCampaignMailer::class)->send($connection, 'not-an-address', 'Hello', '<p>Hi</p>');
        $this->fail('Expected the send to be rejected.');
    } catch (MailerHttpException $exception) {
        expect($exception->getMessage())->toBe('Outlook message create failed.')
            ->and($exception->status)->toBe(400)
            ->and($exception->errorCode)->toBe('ErrorInvalidRecipients')
            ->and(app(SendFailureClassifier::class)->classify($exception))->toBe(SendFailureType::Recipient);
    }
});

test('a revoked refresh token is classified as a connection failure', function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
    ]);

    $connection = MailerConnection::factory()->create([
        'mailer_type' => MailerType::Gmail,
        'smtp_setting' => [
            'access_token' => 'old-access',
            'refresh_token' => 'revoked-token',
            'expires_at' => CarbonImmutable::now()->subMinute()->toIso8601String(),
            'email' => 'user@gmail.com',
        ],
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Token has been expired or revoked.',
        ], 400),
    ]);

    try {
        app(OAuthTokenManager::class)->accessToken($connection);
        $this->fail('Expected the refresh to fail.');
    } catch (MailerHttpException $exception) {
        expect($exception->getMessage())->toBe('Google token refresh failed.')
            ->and($exception->errorCode)->toBe('invalid_grant')
            ->and(app(SendFailureClassifier::class)->classify($exception))->toBe(SendFailureType::Connection);
    }
});

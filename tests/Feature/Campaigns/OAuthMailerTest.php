<?php

use App\Actions\MailerConnections\VerifyMailerConnection;
use App\Contracts\Mail\GmailApi;
use App\Data\SendResult;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Services\Mail\OutlookCampaignMailer;
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

    public function sendRaw(string $accessToken, string $raw): array
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
    );

    expect($result)->toBeInstanceOf(SendResult::class)
        ->and($result->messageId)->toBe('<outlook-1@example.com>')
        ->and($result->threadId)->toBe('conv-1');

    Http::assertSentCount(3);
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

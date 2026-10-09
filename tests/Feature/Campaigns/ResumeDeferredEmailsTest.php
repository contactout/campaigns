<?php

use App\Actions\MailerConnections\CreateOAuthMailerConnection;
use App\Actions\MailerConnections\VerifyMailerConnection;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\SmtpConnectionVerifier;
use Carbon\CarbonImmutable;
use Mockery\MockInterface;

/**
 * Build a deactivated connection with one deferred and one ordinary future email.
 *
 * @param  array<string, mixed>  $connectionAttributes
 * @param  array<string, mixed>|null  $settings
 * @return array{connection: MailerConnection, deferred: CampaignEmail, untouched: CampaignEmail}
 */
function mmosDeferredFixture(array $connectionAttributes = [], ?array $settings = null): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Deactivated,
        ...$connectionAttributes,
    ]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'UTC',
        'settings' => $settings,
    ]);

    $deferred = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'mailer_connection_id' => $connection->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addHour(),
        'data' => [SendEmail::DEFERRED_FOR_CONNECTION => $connection->id, 'send_attempts' => 1],
    ]);

    $untouched = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'mailer_connection_id' => $connection->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addDays(3),
    ]);

    return compact('connection', 'deferred', 'untouched');
}

test('verifying a connection resumes its deferred emails', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fixture = mmosDeferredFixture();

    $this->mock(SmtpConnectionVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once();
    });

    app(VerifyMailerConnection::class)->handle($fixture['connection']);

    $deferred = $fixture['deferred']->fresh();

    expect($deferred->scheduled_at->toDateTimeString())->toBe('2026-02-02 12:00:00')
        ->and($deferred->data)->toBe(['send_attempts' => 1])
        ->and($fixture['untouched']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-05 12:00:00');
});

test('resumed emails wait for the campaign sending window', function () {
    // Saturday; the campaign sends on weekdays from 09:00.
    $this->travelTo(CarbonImmutable::parse('2026-02-07 12:00:00'));

    $fixture = mmosDeferredFixture(settings: [
        'sending_days' => [1, 2, 3, 4, 5],
        'sending_hour_from' => 9,
        'sending_hour_to' => 17,
    ]);

    $this->mock(SmtpConnectionVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once();
    });

    app(VerifyMailerConnection::class)->handle($fixture['connection']);

    expect($fixture['deferred']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00');
});

test('a failed verification leaves deferred emails waiting', function () {
    $fixture = mmosDeferredFixture();
    $scheduledAt = $fixture['deferred']->scheduled_at->toDateTimeString();

    $this->mock(SmtpConnectionVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once()->andThrow(new RuntimeException('Connection refused'));
    });

    app(VerifyMailerConnection::class)->handle($fixture['connection']);

    $deferred = $fixture['deferred']->fresh();

    expect($deferred->scheduled_at->toDateTimeString())->toBe($scheduledAt)
        ->and($deferred->data[SendEmail::DEFERRED_FOR_CONNECTION])->toBe($fixture['connection']->id);
});

test('reconnecting an oauth connection resumes its deferred emails', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fixture = mmosDeferredFixture([
        'mailer_type' => MailerType::Gmail,
        'smtp_setting' => ['email' => 'ada@gmail.com', 'from_email' => 'ada@gmail.com'],
    ]);

    app(CreateOAuthMailerConnection::class)->handle(
        $fixture['connection']->team,
        User::factory()->create(),
        MailerType::Gmail,
        [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'scope' => null,
            'email' => 'ada@gmail.com',
            'name' => 'Ada',
            'provider_user_id' => 'google-1',
        ],
    );

    expect($fixture['connection']->fresh()->status)->toBe(MailerConnectionStatus::Active)
        ->and($fixture['deferred']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-02 12:00:00')
        ->and($fixture['deferred']->fresh()->data)->not->toHaveKey(SendEmail::DEFERRED_FOR_CONNECTION);
});

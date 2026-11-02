<?php

use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Enums\TeamRole;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\CampaignStepScheduler;
use Carbon\CarbonImmutable;

/**
 * Build an active campaign on a deactivated connection, with a second, active one to switch to.
 *
 * @return array{user: User, team: Team, campaign: Campaign, old: MailerConnection, new: MailerConnection}
 */
function connectionSwitchFixture(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $old = MailerConnection::factory()->forTeam($team)->create(['status' => MailerConnectionStatus::Deactivated]);
    $new = MailerConnection::factory()->forTeam($team)->create(['status' => MailerConnectionStatus::Active]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $old->id,
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::now(),
    ]);

    return compact('user', 'team', 'campaign', 'old', 'new');
}

/**
 * Submit the campaign edit form with the given connection.
 *
 * @param  array{user: User, team: Team, campaign: Campaign, old: MailerConnection, new: MailerConnection}  $fixture
 */
function switchCampaignConnection(array $fixture, ?int $connectionId): void
{
    test()->actingAs($fixture['user'])
        ->patch(route('campaigns.update', ['current_team' => $fixture['team']->slug, 'campaign' => $fixture['campaign']]), [
            'name' => $fixture['campaign']->name,
            'timezone' => $fixture['campaign']->timezone,
            'mailer_connection_id' => $connectionId,
        ])
        ->assertRedirect();
}

test('switching a campaign connection moves its unsent emails to the new one', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fixture = connectionSwitchFixture();
    $old = $fixture['old'];

    $deferred = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'mailer_connection_id' => $old->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addMinutes(45),
        'data' => [SendEmail::DEFERRED_FOR_CONNECTION => $old->id, 'send_attempts' => 1],
    ]);

    $later = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'mailer_connection_id' => $old->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addDays(3),
    ]);

    $sent = CampaignEmail::factory()->sent()->create([
        'campaign_id' => $fixture['campaign']->id,
        'mailer_connection_id' => $old->id,
    ]);

    $otherCampaign = CampaignEmail::factory()->create([
        'campaign_id' => Campaign::factory()->forTeam($fixture['team'])->active()->create(['mailer_connection_id' => $old->id])->id,
        'mailer_connection_id' => $old->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addDay(),
    ]);

    switchCampaignConnection($fixture, $fixture['new']->id);

    $deferred->refresh();
    $later->refresh();

    expect($deferred->mailer_connection_id)->toBe($fixture['new']->id)
        ->and($deferred->data)->toBe(['send_attempts' => 1])
        ->and($deferred->scheduled_at->toDateTimeString())->toBe('2026-02-02 12:00:00')
        ->and($later->mailer_connection_id)->toBe($fixture['new']->id)
        ->and($later->scheduled_at->toDateTimeString())->toBe('2026-02-05 12:00:00')
        ->and($sent->fresh()->mailer_connection_id)->toBe($old->id)
        ->and($otherCampaign->fresh()->mailer_connection_id)->toBe($old->id);
});

test('clearing a campaign connection leaves its unsent emails where they are', function () {
    $fixture = connectionSwitchFixture();

    $email = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'mailer_connection_id' => $fixture['old']->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->addHour(),
        'data' => [SendEmail::DEFERRED_FOR_CONNECTION => $fixture['old']->id],
    ]);

    switchCampaignConnection($fixture, null);

    $email->refresh();

    expect($fixture['campaign']->fresh()->mailer_connection_id)->toBeNull()
        ->and($email->mailer_connection_id)->toBe($fixture['old']->id)
        ->and($email->data)->toBe([SendEmail::DEFERRED_FOR_CONNECTION => $fixture['old']->id]);
});

test('the next step is scheduled on the campaign connection after a switch', function () {
    $fixture = connectionSwitchFixture();
    $campaign = $fixture['campaign'];

    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'day' => 0]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'day' => 2]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => RecipientStatus::Active,
    ]);

    // Sent through the old connection just before the campaign was moved.
    $email = CampaignEmail::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $first->id,
        'recipient_id' => $recipient->id,
        'mailer_connection_id' => $fixture['old']->id,
    ]);

    switchCampaignConnection($fixture, $fixture['new']->id);

    app(CampaignStepScheduler::class)->scheduleNextStep($email);

    $next = CampaignEmail::query()
        ->where('recipient_id', $recipient->id)
        ->where('campaign_step_id', $second->id)
        ->sole();

    expect($next->mailer_connection_id)->toBe($fixture['new']->id);
});

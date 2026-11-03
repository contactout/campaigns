<?php

use App\Enums\CampaignStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('campaign connection warnings use the same verification status as connections', function (
    MailerConnectionStatus $status,
    ?string $exception,
    string $displayStatus,
) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => $status,
        'exception_type' => $exception,
    ]);
    MailerConnection::factory()->create();
    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.status', CampaignStatus::Active->value)
            ->where('connection.id', $connection->id)
            ->where('connection.status', $status->value)
            ->where('connection.display_status', $displayStatus)
            ->where('connection.display_status_label', $displayStatus)
            ->missing('connection.smtp_setting')
            ->missing('connection.exception_data')
            ->has('mailerConnections', 1)
            ->where('mailerConnections.0.id', $connection->id)
            ->where('mailerConnections.0.display_status', $displayStatus)
            ->where('mailerConnections.0.display_status_label', $displayStatus)
            ->missing('mailerConnections.0.smtp_setting')
            ->missing('mailerConnections.0.exception_data'));
})->with([
    'verified' => [MailerConnectionStatus::Active, null, 'Verified'],
    'pending' => [MailerConnectionStatus::Pending, null, 'Pending'],
    'deactivated' => [MailerConnectionStatus::Deactivated, null, 'Deactivated'],
    'disconnected' => [MailerConnectionStatus::Disconnected, null, 'Disconnected'],
    'failed' => [MailerConnectionStatus::Deactivated, 'AuthenticationException', 'Failed'],
    'active with a recorded failure' => [MailerConnectionStatus::Active, 'AuthenticationException', 'Failed'],
]);

test('an active campaign can still switch to an inactive connection and expose its warning status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $verified = MailerConnection::factory()->forTeam($team)->create(['status' => MailerConnectionStatus::Active]);
    $inactive = MailerConnection::factory()->forTeam($team)->create(['status' => MailerConnectionStatus::Deactivated]);
    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $verified->id,
    ]);

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => $campaign->name,
            'timezone' => $campaign->timezone,
            'mailer_connection_id' => $inactive->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]));

    expect($campaign->fresh()->mailer_connection_id)->toBe($inactive->id)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Active);

    $this->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connection.id', $inactive->id)
            ->where('connection.display_status', 'Deactivated'));
});

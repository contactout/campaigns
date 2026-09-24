<?php

use App\Enums\CampaignStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\ContactField;
use App\Models\EmailTemplate;
use App\Models\MailerConnection;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function campaignTeamWithMember(?User $user = null): array
{
    $user ??= User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return [$team, $user];
}

beforeEach(function (): void {
    $this->withoutVite();

    // The React pages are delivered in a later phase; assert component names only.
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('guests are redirected to login from campaigns', function () {
    $team = Team::factory()->create();

    $this->get(route('campaigns.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('non members cannot access campaigns', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('campaigns.index', ['current_team' => $team->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('campaigns.store', ['current_team' => $team->slug]), [
            'name' => 'Nope',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();
});

test('members can list only their teams campaigns', function () {
    [$team, $user] = campaignTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $campaign = Campaign::factory()->forTeam($team)->create(['name' => 'Launch']);
    CampaignStep::factory()->forCampaign($campaign)->create();

    Campaign::factory()->forTeam($otherTeam)->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('campaigns.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.name', 'Launch')
            ->where('campaigns.data.0.status', CampaignStatus::Draft->value)
            ->where('campaigns.data.0.status_label', 'Draft')
            ->where('campaigns.data.0.steps_count', 1)
            ->where('campaigns.data.0.recipients_count', 0)
            ->has('filters')
            ->has('statuses')
            ->where('can.create', true));
});

test('members can create a campaign', function () {
    [$team, $user] = campaignTeamWithMember();

    $response = $this->actingAs($user)->post(route('campaigns.store', ['current_team' => $team->slug]), [
        'name' => 'Launch',
        'timezone' => 'UTC',
    ]);

    $campaign = Campaign::query()->where('team_id', $team->id)->firstOrFail();

    $response
        ->assertRedirect(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign created.']);

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->user_id)->toBe($user->id);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'team_id' => $team->id,
        'name' => 'Launch',
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.id', $campaign->id)
            ->where('campaign.name', 'Launch')
            ->where('can.update', true));
});

test('a campaign can only use a mailer connection from its own team', function () {
    [$team, $user] = campaignTeamWithMember();

    $otherTeam = Team::factory()->create();
    $foreignConnection = MailerConnection::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->post(route('campaigns.store', ['current_team' => $team->slug]), [
            'name' => 'Launch',
            'timezone' => 'UTC',
            'mailer_connection_id' => $foreignConnection->id,
        ])
        ->assertSessionHasErrors('mailer_connection_id');

    $this->assertDatabaseCount('campaigns', 0);
});

test('members can view a campaign with its steps and stats', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create(['name' => 'Launch']);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'First']);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.name', 'Launch')
            ->has('steps', 1)
            ->where('steps.0.subject', 'First')
            ->where('stats.steps_count', 1)
            ->where('stats.recipients_count', 0)
            ->has('can'));
});

test('members can update a campaign', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => 'New name',
            'timezone' => 'Europe/London',
        ])
        ->assertRedirect(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign updated.']);

    $campaign->refresh();

    expect($campaign->name)->toBe('New name')
        ->and($campaign->timezone)->toBe('Europe/London');
});

test('members can delete a campaign and its steps', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    CampaignStep::factory()->forCampaign($campaign)->create();

    $this->actingAs($user)
        ->delete(route('campaigns.destroy', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertRedirect(route('campaigns.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign deleted.']);

    $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    $this->assertDatabaseMissing('campaign_steps', ['campaign_id' => $campaign->id]);
});

test('a campaign needs at least one step before it can be started', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.start', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->fresh()->started_at)->toBeNull();
});

test('members can start a campaign with a step', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    CampaignStep::factory()->forCampaign($campaign)->create();

    $this->actingAs($user)
        ->post(route('campaigns.start', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign started.']);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Active)
        ->and($campaign->started_at)->not->toBeNull();
});

test('only active campaigns can be stopped', function () {
    [$team, $user] = campaignTeamWithMember();

    $draft = Campaign::factory()->forTeam($team)->create();
    CampaignStep::factory()->forCampaign($draft)->create();

    $this->actingAs($user)
        ->post(route('campaigns.stop', ['current_team' => $team->slug, 'campaign' => $draft]))
        ->assertSessionHasErrors('campaign');

    expect($draft->fresh()->status)->toBe(CampaignStatus::Draft);

    $active = Campaign::factory()->forTeam($team)->active()->create();

    $this->actingAs($user)
        ->post(route('campaigns.stop', ['current_team' => $team->slug, 'campaign' => $active]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign stopped.']);

    expect($active->fresh()->status)->toBe(CampaignStatus::Stopped);
});

test('members can archive a campaign', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.archive', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign archived.']);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Archived);
});

test('members can duplicate a campaign with its steps in order', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create(['name' => 'Launch', 'status' => CampaignStatus::Active]);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'First']);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'Second']);

    $response = $this->actingAs($user)
        ->post(route('campaigns.duplicate', ['current_team' => $team->slug, 'campaign' => $campaign]));

    $copy = Campaign::query()
        ->where('team_id', $team->id)
        ->whereKeyNot($campaign->id)
        ->firstOrFail();

    $response
        ->assertRedirect(route('campaigns.show', [
            'current_team' => $team->slug,
            'campaign' => $copy,
        ]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign duplicated.']);

    expect($copy->name)->toBe('Copy of Launch')
        ->and($copy->status)->toBe(CampaignStatus::Draft)
        ->and($copy->started_at)->toBeNull()
        ->and($copy->steps()->pluck('subject')->all())->toBe(['First', 'Second'])
        ->and($copy->steps()->pluck('sequence')->all())->toBe([1, 2]);
});

test('a campaign from another team is not found', function () {
    [$team, $user] = campaignTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherCampaign = Campaign::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $otherCampaign]))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $otherCampaign]), [
            'name' => 'Hacked',
            'timezone' => 'UTC',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('campaigns.destroy', ['current_team' => $team->slug, 'campaign' => $otherCampaign]))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('campaigns.start', ['current_team' => $team->slug, 'campaign' => $otherCampaign]))
        ->assertNotFound();
});

test('the campaign show page exposes templates signatures and placeholders', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    EmailTemplate::factory()->forTeam($team)->create(['name' => 'Intro', 'subject' => 'Hi', 'body' => '<p>Hello</p>']);
    Signature::factory()->forTeam($team)->create(['name' => 'Work']);
    ContactField::factory()->forTeam($team)->create(['name' => 'company']);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('templates', 1)
            ->where('templates.0.name', 'Intro')
            ->has('signatures', 1)
            ->where('signatures.0.name', 'Work')
            ->has('placeholders', 4)
            ->where('placeholders.3.name', 'company'));
});

test('the campaign index and show pages expose the teams mailer connections', function () {
    [$team, $user] = campaignTeamWithMember();

    MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Primary',
        'status' => MailerConnectionStatus::Active,
    ]);
    MailerConnection::factory()->forTeam($team)->create(['name' => 'Backup']);

    MailerConnection::factory()->create(['name' => 'Hidden']);

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->get(route('campaigns.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->has('mailerConnections', 2)
            ->where('mailerConnections.0.name', 'Backup')
            ->where('mailerConnections.0.status', MailerConnectionStatus::Pending->value)
            ->where('mailerConnections.1.name', 'Primary')
            ->where('mailerConnections.1.status_label', 'Active')
            ->missing('mailerConnections.0.smtp_setting'));

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('mailerConnections', 2)
            ->where('mailerConnections.0.name', 'Backup'));
});

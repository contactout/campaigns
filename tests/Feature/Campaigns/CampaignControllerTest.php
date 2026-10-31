<?php

use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\EmailTemplate;
use App\Models\LinkClick;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Signature;
use App\Models\Team;
use App\Models\TrackedLink;
use App\Models\Unsubscribe;
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

test('the campaign stats count opens clicks and unsubscribes', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $email = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => EmailStatus::Opened,
        'opened_at' => now(),
    ]);

    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    LinkClick::factory()->create([
        'tracked_link_id' => $link->id,
        'campaign_email_id' => $email->id,
        'recipient_id' => $email->recipient_id,
    ]);

    Unsubscribe::factory()->forTeam($team)->create([
        'campaign_id' => $campaign->id,
        'recipient_id' => $email->recipient_id,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('stats.opened', 1)
            ->where('stats.clicked', 1)
            ->where('stats.unsubscribed', 1));
});

test('the sent stat counts every dispatched email whatever happened after', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    foreach ([EmailStatus::Sent, EmailStatus::Opened, EmailStatus::Replied, EmailStatus::Bounced] as $status) {
        CampaignEmail::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => $status,
            'dispatched_at' => now(),
        ]);
    }

    CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => EmailStatus::Scheduled,
        'dispatched_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stats.emails_sent', 4));
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

test('members can save sending window and tracking settings', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => $campaign->name,
            'timezone' => 'UTC',
            'settings' => [
                'sending_days' => [1, 2, 3, 4, 5],
                'sending_hour_from' => 9,
                'sending_hour_to' => 17,
                'open_tracking' => false,
                'link_tracking' => false,
            ],
        ])
        ->assertRedirect();

    // Asserted key by key: MySQL's json column reorders object keys, so
    // comparing the whole array would depend on storage order.
    $settings = $campaign->refresh()->settings;

    expect($settings['sending_days'])->toBe([1, 2, 3, 4, 5])
        ->and($settings['sending_hour_from'])->toBe(9)
        ->and($settings['sending_hour_to'])->toBe(17)
        ->and($settings['open_tracking'])->toBeFalse()
        ->and($settings['link_tracking'])->toBeFalse();
});

test('the campaign show page exposes the default settings', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaign.settings.sending_days', [1, 2, 3, 4, 5, 6, 7])
            ->where('campaign.settings.sending_hour_from', 0)
            ->where('campaign.settings.sending_hour_to', 0)
            ->where('campaign.settings.open_tracking', true)
            ->where('campaign.settings.link_tracking', true));
});

test('updating a campaign without settings leaves them untouched', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create([
        'settings' => ['sending_days' => [1, 2, 3, 4, 5], 'sending_hour_from' => 9, 'sending_hour_to' => 17],
    ]);

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => 'Renamed',
            'timezone' => 'UTC',
        ])
        ->assertRedirect();

    $campaign->refresh();

    expect($campaign->name)->toBe('Renamed')
        ->and($campaign->settings['sending_days'])->toBe([1, 2, 3, 4, 5])
        ->and($campaign->settings['sending_hour_to'])->toBe(17);
});

test('an invalid sending hour range is rejected', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => $campaign->name,
            'timezone' => 'UTC',
            'settings' => [
                'sending_days' => [1, 2, 3, 4, 5],
                'sending_hour_from' => 17,
                'sending_hour_to' => 9,
            ],
        ])
        ->assertSessionHasErrors('settings.sending_hour_to');

    expect($campaign->refresh()->settings)->toBeNull();
});

test('settings need at least one sending day', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('campaigns.update', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'name' => $campaign->name,
            'timezone' => 'UTC',
            'settings' => [
                'sending_hour_from' => 0,
                'sending_hour_to' => 0,
            ],
        ])
        ->assertSessionHasErrors('settings.sending_days');
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

test('the show page counts sent opened and replied per step', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'First']);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'Second']);

    $email = fn (CampaignStep $step, array $attributes = []): CampaignEmail => CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'status' => EmailStatus::Sent,
        'dispatched_at' => now(),
        ...$attributes,
    ]);

    // Step one: four dispatched, two opened, one of those replied.
    $email($first, ['status' => EmailStatus::Opened, 'opened_at' => now()]);
    $email($first, ['status' => EmailStatus::Opened, 'opened_at' => now()]);
    $email($first, ['status' => EmailStatus::Replied, 'opened_at' => now(), 'replied_at' => now()]);
    $email($first);
    // Step two: one dispatched, nothing opened.
    $email($second);

    // Another campaign's emails, including one that reuses this campaign's step
    // id, must not reach this campaign's metrics.
    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $otherStep = CampaignStep::factory()->forCampaign($otherCampaign)->create(['sequence' => 1]);

    CampaignEmail::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'campaign_step_id' => $otherStep->id,
        'status' => EmailStatus::Opened,
        'dispatched_at' => now(),
        'opened_at' => now(),
        'replied_at' => now(),
    ]);

    CampaignEmail::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'campaign_step_id' => $first->id,
        'status' => EmailStatus::Opened,
        'dispatched_at' => now(),
        'opened_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('steps.0.metrics.sent', 4)
            ->where('steps.0.metrics.opened', 3)
            ->where('steps.0.metrics.replied', 1)
            ->where('steps.0.metrics.opened_rate', 75)
            ->where('steps.0.metrics.replied_rate', 25)
            ->where('steps.1.metrics.sent', 1)
            ->where('steps.1.metrics.opened_rate', 0)
            ->where('steps.1.metrics.replied_rate', 0));
});

test('the recipients payload derives the boards status labels', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $recipient = function (string $name, RecipientStatus $status, int $minutesAgo) use ($team, $campaign): Recipient {
        $contact = Contact::factory()->forTeam($team)->create(['name' => $name]);

        return Recipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => $status,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    };

    $replied = $recipient('Replied Person', RecipientStatus::Active, 6);
    $bounced = $recipient('Bounced Person', RecipientStatus::Active, 5);
    $unsubscribed = $recipient('Unsubscribed Person', RecipientStatus::Unsubscribed, 4);
    $opened = $recipient('Opened Person', RecipientStatus::Active, 3);
    $contacted = $recipient('Contacted Person', RecipientStatus::Active, 2);
    $recipient('Untouched Person', RecipientStatus::Active, 1);

    $email = fn (Recipient $target, array $attributes): CampaignEmail => CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'recipient_id' => $target->id,
        'status' => EmailStatus::Sent,
        'dispatched_at' => now(),
        ...$attributes,
    ]);

    $email($replied, ['status' => EmailStatus::Replied, 'opened_at' => now(), 'replied_at' => now()]);
    $email($bounced, ['status' => EmailStatus::Bounced]);
    $email($opened, ['status' => EmailStatus::Opened, 'opened_at' => now()]);
    $email($contacted, []);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('recipients.data', 6)
            // newest first: latest() orders by created_at
            ->where('recipients.data.0.name', 'Untouched Person')
            ->where('recipients.data.0.display_status', 'not_contacted')
            ->where('recipients.data.0.display_status_label', 'Not contacted')
            ->where('recipients.data.0.status', RecipientStatus::Active->value)
            ->where('recipients.data.0.status_label', 'Active')
            ->where('recipients.data.1.display_status', 'contacted')
            ->where('recipients.data.2.display_status', 'opened')
            ->where('recipients.data.3.display_status', 'unsubscribed')
            ->where('recipients.data.4.display_status', 'bounced')
            ->where('recipients.data.5.display_status', 'replied'));
});

test('start is blocked by an empty subject a failed connection and do not contact recipients', function () {
    [$team, $user] = campaignTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Mailgun relay',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => 'SmtpAuthenticationFailed',
    ]);

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => $connection->id,
        'status' => CampaignStatus::Draft,
    ]);

    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'day' => 1, 'subject' => 'Founders, quick question']);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'day' => 3, 'subject' => '']);

    $recipient = function (ContactStatus $status, Campaign $for) use ($team): Recipient {
        $contact = Contact::factory()->forTeam($team)->create(['status' => $status]);

        return Recipient::factory()->create([
            'campaign_id' => $for->id,
            'contact_id' => $contact->id,
        ]);
    };

    $recipient(ContactStatus::DoNotContact, $campaign);

    // "Do not contact" is also the timestamp: the contact page reports it from
    // `do_not_contact_at`, and SuppressionList blocks on either signal.
    $timestamped = Contact::factory()->forTeam($team)->create([
        'status' => ContactStatus::NotContacted,
        'do_not_contact_at' => now(),
    ]);
    Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $timestamped->id,
    ]);

    // A soft-deleted contact is gone everywhere else, so it is not counted.
    $deleted = Contact::factory()->forTeam($team)->create([
        'status' => ContactStatus::DoNotContact,
    ]);
    Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $deleted->id,
    ]);
    $deleted->delete();

    $recipient(ContactStatus::NotContacted, $campaign);
    $recipient(ContactStatus::NotContacted, $campaign);

    // A do-not-contact contact on another campaign of the same team must not count.
    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $recipient(ContactStatus::DoNotContact, $otherCampaign);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('start.blocked', true)
            ->has('start.reasons', 3)
            ->where('start.reasons.0.message', 'Step 2 (Day 3) has an empty subject')
            ->where('start.reasons.0.step_id', $campaign->steps()->where('sequence', 2)->value('id'))
            ->where('start.reasons.0.blocking', true)
            ->where('start.reasons.1.message', 'The sending connection "Mailgun relay" failed verification')
            ->where('start.reasons.1.type', 'connection')
            ->where('start.reasons.2.message', '2 recipients are marked Do not contact and will be skipped')
            ->where('start.reasons.2.blocking', false));
});

test('start stays enabled when nothing blocks the campaign', function () {
    [$team, $user] = campaignTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Primary',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => null,
    ]);

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => $connection->id,
        'status' => CampaignStatus::Draft,
    ]);

    CampaignStep::factory()->forCampaign($campaign)->create(['subject' => 'Ready']);

    $contact = Contact::factory()->forTeam($team)->create();
    Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('start.blocked', false)
            ->has('start.reasons', 0));
});

test('a campaign without steps or a connection lists both blockers', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => null,
        'status' => CampaignStatus::Draft,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('start.blocked', true)
            ->has('start.reasons', 2)
            ->where('start.reasons.0.message', 'This campaign has no steps yet.')
            ->where('start.reasons.1.message', 'This campaign has no sending connection.'));
});

test('an empty recipient list is not a blocker', function () {
    [$team, $user] = campaignTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'exception_type' => null,
    ]);

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => $connection->id,
        'status' => CampaignStatus::Draft,
    ]);

    CampaignStep::factory()->forCampaign($campaign)->create(['subject' => 'Ready']);

    // The scheduler supports recipients joining a running campaign, so Start
    // stays enabled with nobody enrolled yet.
    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('stats.recipients_count', 0)
            ->where('start.blocked', false)
            ->has('start.reasons', 0));
});

test('a campaign that cannot be started reports no blockers', function () {
    [$team, $user] = campaignTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Mailgun relay',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => 'SmtpAuthenticationFailed',
    ]);

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => $connection->id,
        'status' => CampaignStatus::Active,
    ]);

    CampaignStep::factory()->forCampaign($campaign)->create(['subject' => '']);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.status', CampaignStatus::Active->value)
            ->where('start.blocked', false)
            ->has('start.reasons', 0));
});

test('a recipient whose contact was deleted still renders', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Gone Person']);

    Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
    ]);

    $contact->delete();

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.name', 'Gone Person')
            ->where('recipients.data.0.email', null));
});

test('the recipients tab can be searched by name or email', function () {
    [$team, $user] = campaignTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $ada = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    $grace = Contact::factory()->forTeam($team)->create(['name' => 'Grace Hopper']);

    $ada->identities()->create([
        'team_id' => $team->id,
        'identity_type' => 'email',
        'normalized_value' => 'ada@analytical.engine',
    ]);
    $grace->identities()->create([
        'team_id' => $team->id,
        'identity_type' => 'email',
        'normalized_value' => 'grace@navy.mil',
    ]);

    Recipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $ada->id]);
    Recipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $grace->id]);

    // Same name, different campaign: the search must stay inside this campaign.
    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $otherAda = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    Recipient::factory()->create(['campaign_id' => $otherCampaign->id, 'contact_id' => $otherAda->id]);

    $this->actingAs($user)
        ->get(route('campaigns.show', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'recipients_q' => 'Ada',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('recipientsSearch', 'Ada')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.name', 'Ada Lovelace'));

    // The email branch of the search finds the same recipient by address.
    $this->actingAs($user)
        ->get(route('campaigns.show', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'recipients_q' => 'grace@navy',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.name', 'Grace Hopper'));
});

test('the show page exposes the campaigns sending connection without its credentials', function () {
    [$team, $user] = campaignTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Primary',
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'from_email' => 'sam@northwind.io',
            'password' => 'super-secret',
            'host' => 'smtp.northwind.io',
        ],
    ]);

    $campaign = Campaign::factory()->forTeam($team)->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('connection.name', 'Primary')
            ->where('connection.from_email', 'sam@northwind.io')
            ->where('connection.status', MailerConnectionStatus::Active->value)
            ->missing('connection.smtp_setting')
            ->missing('connection.password'));
});

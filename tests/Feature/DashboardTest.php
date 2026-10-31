<?php

use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TemplateFolder;
use App\Models\User;
use Carbon\CarbonInterface;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes setup flags for the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('setup.has_connection', false)
            ->where('setup.has_contact', false)
            ->where('setup.has_campaign', false),
        );

    MailerConnection::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);
    Contact::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);
    Campaign::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $user->switchTeam($team);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.has_connection', true)
            ->where('setup.has_contact', true)
            ->where('setup.has_campaign', true),
        );
});

test('dashboard setup flags ignore other teams data', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherOwner = User::factory()->create();
    $otherTeam = $otherOwner->currentTeam;

    MailerConnection::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
    ]);
    Contact::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
    ]);
    Campaign::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
    ]);

    $user->switchTeam($team);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.has_connection', false)
            ->where('setup.has_contact', false)
            ->where('setup.has_campaign', false),
        );
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes invitations with a role that can no longer be assigned', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role' => TeamRole::Owner,
        'invited_by' => $owner->id,
    ]);

    $memberInvitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $memberInvitation->code),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard reports 30 day email metrics and their previous window deltas', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaign = Campaign::factory()->forTeam($team)->create([
        'user_id' => $user->id,
    ]);

    $send = function (CarbonInterface $dispatchedAt, bool $opened, bool $replied) use ($campaign): void {
        CampaignEmail::factory()->create([
            'campaign_id' => $campaign->id,
            'dispatched_at' => $dispatchedAt,
            'opened_at' => $opened ? $dispatchedAt->copy()->addHour() : null,
            'replied_at' => $replied ? $dispatchedAt->copy()->addHours(2) : null,
        ]);
    };

    // Current window: 4 sent, 2 opened (50%), 1 replied (25%).
    $send(now()->subDays(2), true, true);
    $send(now()->subDays(4), true, false);
    $send(now()->subDays(8), false, false);
    $send(now()->subDays(20), false, false);

    // Previous window: 2 sent, 1 opened (50%), 0 replied (0%).
    $send(now()->subDays(35), true, false);
    $send(now()->subDays(45), false, false);

    Campaign::factory()->forTeam($team)->active()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.emails_sent', 4)
            // JSON drops the trailing .0, so 50.0 arrives as 50.
            ->where('metrics.open_rate', 50)
            ->where('metrics.reply_rate', 25)
            ->where('metrics.active_campaigns', 1)
            ->where('metrics.emails_sent_delta', 100)
            ->where('metrics.open_rate_delta', 0)
            ->where('metrics.reply_rate_delta', 25),
        );
});

test('dashboard reports the counts behind the jump back in rows', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Contact::factory()->count(2)->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);
    ContactList::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);
    Campaign::factory()->forTeam($team)->active()->create([
        'user_id' => $user->id,
    ]);
    Campaign::factory()->forTeam($team)->create([
        'user_id' => $user->id,
    ]);
    $folder = TemplateFolder::factory()->forTeam($team)->create([
        'user_id' => $user->id,
    ]);
    EmailTemplate::factory()->forTeam($team)->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);
    MailerConnection::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);
    MailerConnection::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'exception_type' => 'SMTPException',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('jumpBackIn.contacts', 2)
            ->where('jumpBackIn.lists', 1)
            ->where('jumpBackIn.active_campaigns', 1)
            ->where('jumpBackIn.draft_campaigns', 1)
            ->where('jumpBackIn.templates', 1)
            ->where('jumpBackIn.template_folders', 1)
            ->where('jumpBackIn.connections', 2)
            ->where('jumpBackIn.connections_needing_attention', 1)
            ->has('mailerConnections', 2),
        );
});

test('dashboard metrics ignore other teams data and leave empty rates null', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherOwner = User::factory()->create();
    $otherTeam = $otherOwner->currentTeam;

    $otherCampaign = Campaign::factory()->forTeam($otherTeam)->active()->create([
        'user_id' => $otherOwner->id,
    ]);

    CampaignEmail::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'dispatched_at' => now()->subDay(),
        'opened_at' => now(),
    ]);

    Contact::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
    ]);
    ContactList::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
    ]);
    EmailTemplate::factory()->forTeam($otherTeam)->create([
        'user_id' => $otherOwner->id,
    ]);
    TemplateFolder::factory()->forTeam($otherTeam)->create([
        'user_id' => $otherOwner->id,
    ]);
    MailerConnection::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherOwner->id,
        'exception_type' => 'SMTPException',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.emails_sent', 0)
            ->where('metrics.open_rate', null)
            ->where('metrics.reply_rate', null)
            ->where('metrics.active_campaigns', 0)
            ->where('metrics.emails_sent_delta', null)
            ->where('metrics.open_rate_delta', null)
            ->where('metrics.reply_rate_delta', null)
            ->where('jumpBackIn.contacts', 0)
            ->where('jumpBackIn.lists', 0)
            ->where('jumpBackIn.active_campaigns', 0)
            ->where('jumpBackIn.draft_campaigns', 0)
            ->where('jumpBackIn.templates', 0)
            ->where('jumpBackIn.template_folders', 0)
            ->where('jumpBackIn.connections', 0)
            ->where('jumpBackIn.connections_needing_attention', 0)
            ->has('mailerConnections', 0),
        );
});

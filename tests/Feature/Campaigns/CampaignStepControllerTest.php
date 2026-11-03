<?php

use App\Actions\Campaigns\DeleteCampaignStep;
use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\CampaignStepScheduler;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function campaignStepTeamWithMember(?User $user = null): array
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

test('non members cannot manage campaign steps', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.steps.store', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'day' => 0,
        ])
        ->assertForbidden();
});

test('creating a step appends it to the campaign sequence', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'First']);

    $this->actingAs($user)
        ->post(route('campaigns.steps.store', ['current_team' => $team->slug, 'campaign' => $campaign]), [
            'subject' => 'Second',
            'body' => '<p>Second body</p>',
            'day' => 2,
            'time' => '10:30',
            'is_threaded' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Step created.']);

    $this->assertDatabaseHas('campaign_steps', [
        'campaign_id' => $campaign->id,
        'sequence' => 2,
        'subject' => 'Second',
        'day' => 2,
        'is_threaded' => true,
    ]);
});

test('members can update a step', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'Old']);

    $this->actingAs($user)
        ->patch(route('campaigns.steps.update', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $step,
        ]), [
            'subject' => 'New',
            'body' => '<p>New body</p>',
            'day' => 5,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Step updated.']);

    $step->refresh();

    expect($step->subject)->toBe('New')
        ->and($step->day)->toBe(5)
        ->and($step->sequence)->toBe(1);
});

test('deleting a step normalises the remaining sequence', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'A']);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'B']);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3, 'subject' => 'C']);

    $this->actingAs($user)
        ->delete(route('campaigns.steps.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $first,
        ]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Step deleted.']);

    $this->assertDatabaseMissing('campaign_steps', ['id' => $first->id]);

    expect($campaign->steps()->pluck('subject', 'sequence')->all())->toBe([
        1 => 'B',
        2 => 'C',
    ]);
});

/**
 * Create an active recipient of the campaign with an email for the given step.
 */
function campaignStepRecipientWithEmail(Campaign $campaign, CampaignStep $step, EmailStatus $status, RecipientStatus $recipientStatus = RecipientStatus::Active): Recipient
{
    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => Contact::factory()->create()->id,
        'status' => $recipientStatus,
    ]);

    CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $recipient->id,
        'status' => $status,
    ]);

    return $recipient;
}

test('deleting a step moves recipients waiting on it on to the following step', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00'));

    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
    ]);
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'day' => 0, 'time' => '09:00:00']);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'day' => 3, 'time' => '09:00:00']);
    $third = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3, 'day' => 7, 'time' => '09:00:00']);

    // Step 1 went out; step 2 is the next email waiting to send.
    $waiting = campaignStepRecipientWithEmail($campaign, $first, EmailStatus::Sent);
    CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $second->id,
        'recipient_id' => $waiting->id,
        'status' => EmailStatus::Scheduled,
    ]);

    // A recipient that replied is out of the sequence and stays out.
    $replied = campaignStepRecipientWithEmail($campaign, $second, EmailStatus::Scheduled, RecipientStatus::Replied);

    $this->actingAs($user)
        ->delete(route('campaigns.steps.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $second,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('campaign_steps', ['id' => $second->id]);

    $next = CampaignEmail::query()
        ->where('recipient_id', $waiting->id)
        ->where('campaign_step_id', $third->id)
        ->sole();

    expect($next->status)->toBe(EmailStatus::Scheduled)
        ->and($next->scheduled_at->toDateTimeString())->toBe('2026-01-08 09:00:00')
        ->and(CampaignEmail::query()->where('recipient_id', $replied->id)->exists())->toBeFalse()
        ->and($replied->refresh()->status)->toBe(RecipientStatus::Replied);
});

test('deleting the last step completes recipients waiting on it', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->active()->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $last = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);

    $recipient = campaignStepRecipientWithEmail($campaign, $first, EmailStatus::Sent);
    CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $last->id,
        'recipient_id' => $recipient->id,
        'status' => EmailStatus::Scheduled,
    ]);

    $this->actingAs($user)
        ->delete(route('campaigns.steps.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $last,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($recipient->refresh()->status)->toBe(RecipientStatus::Completed);
});

test('step deletion eligibility matches the server guard for every email status', function (EmailStatus $status, bool $canDelete) {
    [$team, $user] = campaignStepTeamWithMember();
    $campaign = Campaign::factory()->forTeam($team)->active()->create();
    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $empty = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);

    campaignStepRecipientWithEmail($campaign, $step, $status);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('steps.0.id', $step->id)
            ->where('steps.0.can_delete', $canDelete)
            ->where('steps.1.id', $empty->id)
            ->where('steps.1.can_delete', true));

    $response = $this->delete(route('campaigns.steps.destroy', [
        'current_team' => $team->slug,
        'campaign' => $campaign,
        'step' => $step,
    ]))->assertRedirect();

    if ($canDelete) {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('campaign_steps', ['id' => $step->id]);
    } else {
        $response->assertSessionHasErrors('campaign');
        $this->assertDatabaseHas('campaign_steps', ['id' => $step->id]);
    }
})->with([
    'pending' => [EmailStatus::Pending, true],
    'scheduled' => [EmailStatus::Scheduled, true],
    'sent' => [EmailStatus::Sent, false],
    'delivered' => [EmailStatus::Delivered, false],
    'opened' => [EmailStatus::Opened, false],
    'replied' => [EmailStatus::Replied, false],
    'bounced' => [EmailStatus::Bounced, false],
    'failed' => [EmailStatus::Failed, false],
]);

test('a step whose emails have gone out cannot be deleted', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->active()->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);

    $recipient = campaignStepRecipientWithEmail($campaign, $first, EmailStatus::Sent);

    $this->actingAs($user)
        ->delete(route('campaigns.steps.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $first,
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('campaign');

    $this->assertDatabaseHas('campaign_steps', ['id' => $first->id, 'sequence' => 1]);
    $this->assertDatabaseHas('campaign_emails', [
        'campaign_step_id' => $first->id,
        'recipient_id' => $recipient->id,
    ]);
});

test('a send that finishes after a later step is deleted queues the step after it', function () {
    $campaign = Campaign::factory()->active()->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);
    $third = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3]);

    $recipient = campaignStepRecipientWithEmail($campaign, $first, EmailStatus::Sent);

    // The worker loaded the step-1 email before step 2 was deleted.
    $email = CampaignEmail::query()->with(['campaign', 'step', 'recipient'])->where('recipient_id', $recipient->id)->sole();

    app(DeleteCampaignStep::class)->handle($second);

    app(CampaignStepScheduler::class)->scheduleNextStep($email);

    $next = CampaignEmail::query()->where('recipient_id', $recipient->id)->where('campaign_step_id', $third->id)->sole();

    expect($next->status)->toBe(EmailStatus::Scheduled)
        ->and($recipient->refresh()->status)->toBe(RecipientStatus::Active);
});

test('a send that finishes for a deleted step leaves the recipient on the following step', function () {
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);
    $third = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3]);

    $recipient = campaignStepRecipientWithEmail($campaign, $second, EmailStatus::Scheduled);

    // The worker loaded the step-2 email and was mid-send when step 2 was
    // deleted. The deletion moves the recipient on to step 3, and step 3 is
    // renumbered to the sequence step 2 had.
    $email = CampaignEmail::query()->with(['campaign', 'step', 'recipient'])->where('recipient_id', $recipient->id)->sole();

    app(DeleteCampaignStep::class)->handle($second);

    app(CampaignStepScheduler::class)->scheduleNextStep($email);

    expect($recipient->refresh()->status)->toBe(RecipientStatus::Active)
        ->and(CampaignEmail::query()->where('recipient_id', $recipient->id)->pluck('campaign_step_id')->all())->toBe([$third->id]);
});

test('members can reorder campaign steps', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $a = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'A']);
    $b = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'B']);
    $c = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3, 'subject' => 'C']);

    $this->actingAs($user)
        ->post(route('campaigns.steps.reorder', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'steps' => [$c->id, $a->id, $b->id],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Steps reordered.']);

    expect($campaign->steps()->pluck('subject', 'sequence')->all())->toBe([
        1 => 'C',
        2 => 'A',
        3 => 'B',
    ]);
});

test('reordering rejects steps from another campaign', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);

    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $foreignStep = CampaignStep::factory()->forCampaign($otherCampaign)->create(['sequence' => 1]);

    $this->actingAs($user)
        ->post(route('campaigns.steps.reorder', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'steps' => [$step->id, $foreignStep->id],
        ])
        ->assertSessionHasErrors('steps.1');
});

test('reordering rejects a subset of the campaign steps', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $a = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'A']);
    $b = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'B']);
    $c = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3, 'subject' => 'C']);

    $this->actingAs($user)
        ->post(route('campaigns.steps.reorder', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'steps' => [$c->id],
        ])
        ->assertSessionHasErrors('steps');

    expect($campaign->steps()->pluck('subject', 'sequence')->all())->toBe([
        1 => 'A',
        2 => 'B',
        3 => 'C',
    ]);
});

test('reordering rejects duplicate step ids', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $a = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'subject' => 'A']);
    $b = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'subject' => 'B']);

    $this->actingAs($user)
        ->post(route('campaigns.steps.reorder', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'steps' => [$b->id, $b->id, $a->id],
        ])
        ->assertSessionHasErrors('steps.0');

    expect($campaign->steps()->pluck('subject', 'sequence')->all())->toBe([
        1 => 'A',
        2 => 'B',
    ]);
});

test('campaign steps sharing a sequence are ordered by id', function () {
    $campaign = Campaign::factory()->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);

    expect($campaign->steps()->getQuery()->getQuery()->orders)->toBe([
        ['column' => 'sequence', 'direction' => 'asc'],
        ['column' => 'id', 'direction' => 'asc'],
    ])->and($campaign->steps()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('a step from another campaign or team is not found', function () {
    [$team, $user] = campaignStepTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $foreignStep = CampaignStep::factory()->forCampaign($otherCampaign)->create();

    $this->actingAs($user)
        ->patch(route('campaigns.steps.update', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $foreignStep,
        ]), [
            'subject' => 'Nope',
            'body' => 'Nope',
            'day' => 0,
        ])
        ->assertNotFound();

    $otherTeam = Team::factory()->create();
    $crossTeamCampaign = Campaign::factory()->forTeam($otherTeam)->create();
    $crossTeamStep = CampaignStep::factory()->forCampaign($crossTeamCampaign)->create();

    $this->actingAs($user)
        ->delete(route('campaigns.steps.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'step' => $crossTeamStep,
        ]))
        ->assertNotFound();
});

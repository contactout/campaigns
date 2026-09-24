<?php

use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Team;
use App\Models\User;

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

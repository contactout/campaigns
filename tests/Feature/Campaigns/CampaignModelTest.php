<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;

test('campaign factory creates a valid campaign', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->exists)->toBeTrue()
        ->and($campaign->status)->toBe(CampaignStatus::Draft);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Draft->value,
        'timezone' => 'UTC',
    ]);
});

test('campaign belongs to a team and a user', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $campaign = Campaign::factory()->for($team)->for($user)->create();

    expect($campaign->team->is($team))->toBeTrue()
        ->and($campaign->user->is($user))->toBeTrue();
});

test('campaign status is cast to an enum', function () {
    $campaign = Campaign::factory()->active()->create();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Active)
        ->and($campaign->fresh()->started_at)->not->toBeNull();
});

test('campaign has many steps ordered by sequence', function () {
    $campaign = Campaign::factory()->create();

    CampaignStep::factory()->for($campaign)->create(['sequence' => 2]);
    CampaignStep::factory()->for($campaign)->create(['sequence' => 1]);

    expect($campaign->steps()->pluck('sequence')->all())->toBe([1, 2]);
});

test('campaign has many recipients', function () {
    $campaign = Campaign::factory()->create();

    Recipient::factory()->count(2)->create(['campaign_id' => $campaign->id]);

    expect($campaign->recipients)->toHaveCount(2);
});

test('campaign has many emails', function () {
    $campaign = Campaign::factory()->create();

    CampaignEmail::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    expect($campaign->emails)->toHaveCount(3);
});

test('forTeam scope only returns campaigns for the given team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    Campaign::factory()->for($team)->create();
    Campaign::factory()->for($otherTeam)->create();

    expect(Campaign::forTeam($team->id)->count())->toBe(1);
});

test('active scope only returns active campaigns', function () {
    Campaign::factory()->create();
    Campaign::factory()->active()->create();

    expect(Campaign::active()->count())->toBe(1);
});

<?php

use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('team members can view any campaign for their team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('viewAny', [Campaign::class, $team]))->toBeTrue();
});

test('team members can create a campaign', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('create', [Campaign::class, $team]))->toBeTrue();
});

test('team members can view update and delete a campaign', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $campaign = Campaign::factory()->for($team)->create();

    expect(Gate::forUser($user)->allows('view', $campaign))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $campaign))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $campaign))->toBeTrue();
});

test('non members cannot view any campaign for a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', [Campaign::class, $team]))->toBeFalse();
});

test('non members cannot create a campaign', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('create', [Campaign::class, $team]))->toBeFalse();
});

test('non members cannot view update or delete a campaign', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->for($team)->create();

    expect(Gate::forUser($user)->allows('view', $campaign))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $campaign))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $campaign))->toBeFalse();
});

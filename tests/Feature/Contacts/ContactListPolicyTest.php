<?php

use App\Enums\TeamRole;
use App\Models\ContactList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('team members can view any list for their team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('viewAny', [ContactList::class, $team]))->toBeTrue();
});

test('team members can create a list', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('create', [ContactList::class, $team]))->toBeTrue();
});

test('team members can view update and delete a list', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $list = ContactList::factory()->for($team)->create();

    expect(Gate::forUser($user)->allows('view', $list))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $list))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $list))->toBeTrue();
});

test('non members cannot view any list for a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', [ContactList::class, $team]))->toBeFalse();
});

test('non members cannot create a list', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('create', [ContactList::class, $team]))->toBeFalse();
});

test('non members cannot view update or delete a list', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $list = ContactList::factory()->for($team)->create();

    expect(Gate::forUser($user)->allows('view', $list))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $list))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $list))->toBeFalse();
});

<?php

use App\Enums\TeamRole;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('team members can view any contact for their team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('viewAny', [Contact::class, $team]))->toBeTrue();
});

test('team members can create a contact', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect(Gate::forUser($user)->allows('create', [Contact::class, $team]))->toBeTrue();
});

test('team members can view update and delete a contact', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $contact = Contact::factory()->forTeam($team)->create();

    expect(Gate::forUser($user)->allows('view', $contact))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $contact))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $contact))->toBeTrue();
});

test('non members cannot view any contact for a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', [Contact::class, $team]))->toBeFalse();
});

test('non members cannot create a contact', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($user)->allows('create', [Contact::class, $team]))->toBeFalse();
});

test('non members cannot view update or delete a contact', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $contact = Contact::factory()->forTeam($team)->create();

    expect(Gate::forUser($user)->allows('view', $contact))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $contact))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $contact))->toBeFalse();
});

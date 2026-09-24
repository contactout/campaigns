<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Team;
use Illuminate\Database\QueryException;

test('list factory creates a valid list', function () {
    $list = ContactList::factory()->create();

    expect($list->exists)->toBeTrue()
        ->and($list->is_default)->toBeFalse();
});

test('default state marks the list as default', function () {
    $list = ContactList::factory()->default()->create();

    expect($list->fresh()->is_default)->toBeTrue();
});

test('list belongs to many contacts through the pivot', function () {
    $list = ContactList::factory()->create();
    $contact = Contact::factory()->create(['team_id' => $list->team_id]);

    $list->contacts()->attach($contact);

    expect($list->contacts)->toHaveCount(1)
        ->and($list->contacts->first()->is($contact))->toBeTrue();
});

test('a contact can only be attached to a list once', function () {
    $list = ContactList::factory()->create();
    $contact = Contact::factory()->create(['team_id' => $list->team_id]);

    $list->contacts()->attach($contact);

    expect(fn () => $list->contacts()->attach($contact))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('contact_list', 1);
});

test('only one default list is kept per team', function () {
    $team = Team::factory()->create();

    $first = ContactList::factory()->for($team)->default()->create();
    $second = ContactList::factory()->for($team)->default()->create();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and(ContactList::where('team_id', $team->id)->where('is_default', true)->count())->toBe(1);
});

test('settings are cast to an array', function () {
    $list = ContactList::factory()->create(['settings' => ['columns' => ['name']]]);

    expect($list->fresh()->settings)->toBe(['columns' => ['name']]);
});

test('forTeam scope only returns lists for the given team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    ContactList::factory()->for($team)->create();
    ContactList::factory()->for($otherTeam)->create();

    expect(ContactList::forTeam($team->id)->count())->toBe(1);
});

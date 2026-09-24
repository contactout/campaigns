<?php

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\ContactList;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;

test('contact factory creates a valid contact', function () {
    $contact = Contact::factory()->create();

    expect($contact->exists)->toBeTrue()
        ->and($contact->status)->toBe(ContactStatus::NotContacted)
        ->and($contact->source)->toBe('manual');
});

test('contact belongs to a team and a creator', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $contact = Contact::factory()->forTeam($team)->create(['user_id' => $user->id]);

    expect($contact->team->is($team))->toBeTrue()
        ->and($contact->user->is($user))->toBeTrue();
});

test('contact status is cast to an enum', function () {
    $contact = Contact::factory()->create(['status' => ContactStatus::Replied]);

    expect($contact->fresh()->status)->toBe(ContactStatus::Replied);
});

test('contact has many identities', function () {
    $contact = Contact::factory()->create();

    ContactIdentity::factory()->count(2)->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->id,
    ]);

    expect($contact->identities)->toHaveCount(2);
});

test('contact email helper returns the email identity value', function () {
    $contact = Contact::factory()->create();

    ContactIdentity::factory()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'ada@example.com',
    ]);
    ContactIdentity::factory()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Phone,
        'normalized_value' => '5551234',
    ]);

    expect($contact->fresh()->email())->toBe('ada@example.com');
});

test('contact email helper returns null without an email identity', function () {
    $contact = Contact::factory()->create();

    expect($contact->fresh()->email())->toBeNull();
});

test('contact belongs to many lists through the pivot', function () {
    $contact = Contact::factory()->create();
    $list = ContactList::factory()->create(['team_id' => $contact->team_id]);

    $contact->lists()->attach($list);

    expect($contact->lists)->toHaveCount(1)
        ->and($contact->lists->first()->is($list))->toBeTrue();
});

test('contact has many recipients', function () {
    $contact = Contact::factory()->create();
    $firstCampaign = Campaign::factory()->for($contact->team)->create();
    $secondCampaign = Campaign::factory()->for($contact->team)->create();

    Recipient::factory()->for($firstCampaign)->create(['contact_id' => $contact->id]);
    Recipient::factory()->for($secondCampaign)->create(['contact_id' => $contact->id]);

    expect($contact->recipients)->toHaveCount(2);
});

test('contact uses soft deletes', function () {
    $contact = Contact::factory()->create();

    $contact->delete();

    expect($contact->trashed())->toBeTrue()
        ->and(Contact::query()->count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1);
});

test('forTeam scope only returns contacts for the given team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    Contact::factory()->forTeam($team)->create();
    Contact::factory()->forTeam($otherTeam)->create();

    expect(Contact::forTeam($team->id)->count())->toBe(1);
});

test('notDoNotContact scope excludes contacts flagged do not contact', function () {
    Contact::factory()->create();
    Contact::factory()->create(['status' => ContactStatus::DoNotContact]);

    expect(Contact::notDoNotContact()->count())->toBe(1);
});

<?php

use App\Enums\ContactIdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Team;
use Illuminate\Database\QueryException;

test('email identity normalizes by trimming and lowercasing', function () {
    expect(ContactIdentityType::Email->normalize('  Ada@Example.COM '))->toBe('ada@example.com');
});

test('phone identity normalizes by stripping non-digits', function () {
    expect(ContactIdentityType::Phone->normalize('+1 (555) 123-4567'))->toBe('15551234567');
});

test('identity type is cast to an enum', function () {
    $identity = ContactIdentity::factory()->create(['identity_type' => ContactIdentityType::Phone]);

    expect($identity->fresh()->identity_type)->toBe(ContactIdentityType::Phone);
});

test('identity belongs to a contact', function () {
    $contact = Contact::factory()->create();

    $identity = ContactIdentity::factory()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->id,
    ]);

    expect($identity->contact->is($contact))->toBeTrue();
});

test('normalized value must be unique per team and identity type', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->forTeam($team)->create();

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'dup@example.com',
    ]);

    expect(fn () => ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => Contact::factory()->forTeam($team)->create()->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'dup@example.com',
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseCount('contact_identities', 1);
});

test('the same normalized value can exist for different teams', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => Contact::factory()->forTeam($team)->create()->id,
        'normalized_value' => 'shared@example.com',
    ]);
    ContactIdentity::factory()->create([
        'team_id' => $otherTeam->id,
        'contact_id' => Contact::factory()->forTeam($otherTeam)->create()->id,
        'normalized_value' => 'shared@example.com',
    ]);

    expect(ContactIdentity::where('normalized_value', 'shared@example.com')->count())->toBe(2);
});

test('forType scope only returns identities of the given type', function () {
    ContactIdentity::factory()->create(['identity_type' => ContactIdentityType::Email]);
    ContactIdentity::factory()->create(['identity_type' => ContactIdentityType::Phone]);

    expect(ContactIdentity::forType(ContactIdentityType::Email)->count())->toBe(1);
});

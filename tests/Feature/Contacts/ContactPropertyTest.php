<?php

use App\Enums\TeamRole;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactProperty;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function contactPropertyTeamWithMember(?User $user = null): array
{
    $user ??= User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return [$team, $user];
}

beforeEach(function (): void {
    $this->withoutVite();

    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('members can set a contact property value', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', [
            'current_team' => $team->slug,
            'contact' => $contact,
        ]), [
            'contact_field_id' => $field->id,
            'value' => 'Acme',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Contact updated.']);

    $this->assertDatabaseHas('contact_properties', [
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Acme',
    ]);
});

test('setting a property twice updates the existing row', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();

    $route = route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]);

    $this->actingAs($user)->patch($route, ['contact_field_id' => $field->id, 'value' => 'Acme'])->assertRedirect();
    $this->actingAs($user)->patch($route, ['contact_field_id' => $field->id, 'value' => 'Globex'])->assertRedirect();

    expect($contact->properties()->count())->toBe(1)
        ->and($contact->propertyValue($field->id))->toBe('Globex');
});

test('clearing a contact property value deletes the row', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();
    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Acme',
    ]);

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]), [
            'contact_field_id' => $field->id,
            'value' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseMissing('contact_properties', [
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
    ]);
});

test('an unknown contact field id is rejected', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]), [
            'contact_field_id' => 999999,
            'value' => 'Acme',
        ])
        ->assertSessionHasErrors('contact_field_id');
});

test('a contact field from another team is rejected', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $otherTeam = Team::factory()->create();
    $otherField = ContactField::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]), [
            'contact_field_id' => $otherField->id,
            'value' => 'Acme',
        ])
        ->assertSessionHasErrors('contact_field_id');
});

test('non members cannot set a contact property', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]), [
            'contact_field_id' => $field->id,
            'value' => 'Acme',
        ])
        ->assertForbidden();
});

test('a contact from another team cannot have a property set', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $field = ContactField::factory()->forTeam($team)->create();
    $otherTeam = Team::factory()->create();
    $otherContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $otherContact]), [
            'contact_field_id' => $field->id,
            'value' => 'Acme',
        ])
        ->assertNotFound();
});

test('contact properties are included on the contacts index', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();
    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Acme',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where("contacts.data.0.properties.{$field->id}", 'Acme'));
});

test('contact properties are included on the contact page', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);
    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Acme',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->has('fields', 1)
            ->where('fields.0.name', 'Company')
            ->where("properties.{$field->id}", 'Acme'));
});

test('the contact property value is limited in length', function () {
    [$team, $user] = contactPropertyTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    $field = ContactField::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.property', ['current_team' => $team->slug, 'contact' => $contact]), [
            'contact_field_id' => $field->id,
            'value' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('value');
});

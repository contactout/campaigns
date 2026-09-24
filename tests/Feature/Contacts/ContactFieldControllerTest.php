<?php

use App\Enums\PlaceholderType;
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
function contactFieldTeamWithMember(?User $user = null): array
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

test('non members cannot create a contact field', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->post(route('contact-fields.store', ['current_team' => $team->slug]), [
            'name' => 'Company',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertForbidden();
});

test('members can create a contact field', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $this->actingAs($user)
        ->post(route('contact-fields.store', ['current_team' => $team->slug]), [
            'name' => 'Company',
            'fallback' => 'Unknown',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Field created.']);

    $this->assertDatabaseHas('contact_fields', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Company',
        'type' => PlaceholderType::Text->value,
        'fallback' => 'Unknown',
        'position' => 1,
    ]);
});

test('new fields are appended after existing ones', function () {
    [$team, $user] = contactFieldTeamWithMember();

    ContactField::factory()->forTeam($team)->create(['position' => 4]);

    $this->actingAs($user)
        ->post(route('contact-fields.store', ['current_team' => $team->slug]), [
            'name' => 'Role',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertRedirect();

    expect(ContactField::query()->where('name', 'Role')->value('position'))->toBe(5);
});

test('contact fields are listed on the contacts index', function () {
    [$team, $user] = contactFieldTeamWithMember();

    ContactField::factory()->forTeam($team)->create([
        'name' => 'Second',
        'type' => PlaceholderType::Number,
        'fallback' => null,
        'position' => 2,
    ]);

    ContactField::factory()->forTeam($team)->create([
        'name' => 'First',
        'type' => PlaceholderType::Date,
        'fallback' => 'N/A',
        'position' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('fields', 2)
            ->where('fields.0.name', 'First')
            ->where('fields.0.type', PlaceholderType::Date->value)
            ->where('fields.0.type_label', PlaceholderType::Date->label())
            ->where('fields.0.fallback', 'N/A')
            ->where('fields.1.name', 'Second'));
});

test('a field name is unique within a team', function () {
    [$team, $user] = contactFieldTeamWithMember();

    ContactField::factory()->forTeam($team)->create(['name' => 'Company']);

    $this->actingAs($user)
        ->post(route('contact-fields.store', ['current_team' => $team->slug]), [
            'name' => 'Company',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertSessionHasErrors('name');

    expect($team->contactFields()->count())->toBe(1);
});

test('the same field name can be used by another team', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $otherTeam = Team::factory()->create();
    ContactField::factory()->forTeam($otherTeam)->create(['name' => 'Company']);

    $this->actingAs($user)
        ->post(route('contact-fields.store', ['current_team' => $team->slug]), [
            'name' => 'Company',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('members can update a contact field', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $field = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);

    $this->actingAs($user)
        ->patch(route('contact-fields.update', ['current_team' => $team->slug, 'field' => $field]), [
            'name' => 'Employer',
            'fallback' => 'Acme',
            'type' => PlaceholderType::Number->value,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Field updated.']);

    $field->refresh();

    expect($field->name)->toBe('Employer')
        ->and($field->fallback)->toBe('Acme')
        ->and($field->type)->toBe(PlaceholderType::Number);
});

test('updating a field with its own name passes uniqueness', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $field = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);

    $this->actingAs($user)
        ->patch(route('contact-fields.update', ['current_team' => $team->slug, 'field' => $field]), [
            'name' => 'Company',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('members can delete a contact field and its properties', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $field = ContactField::factory()->forTeam($team)->create();
    $contact = Contact::factory()->forTeam($team)->create();
    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Acme',
    ]);

    $this->actingAs($user)
        ->delete(route('contact-fields.destroy', ['current_team' => $team->slug, 'field' => $field]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Field deleted.']);

    $this->assertDatabaseMissing('contact_fields', ['id' => $field->id]);
    $this->assertDatabaseMissing('contact_properties', ['contact_field_id' => $field->id]);
});

test('non members cannot update or delete a contact field', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $field = ContactField::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contact-fields.update', ['current_team' => $team->slug, 'field' => $field]), [
            'name' => 'Hacked',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('contact-fields.destroy', ['current_team' => $team->slug, 'field' => $field]))
        ->assertForbidden();
});

test('a contact field from another team is not found', function () {
    [$team, $user] = contactFieldTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherField = ContactField::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('contact-fields.update', ['current_team' => $team->slug, 'field' => $otherField]), [
            'name' => 'Hijacked',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('contact-fields.destroy', ['current_team' => $team->slug, 'field' => $otherField]))
        ->assertNotFound();
});

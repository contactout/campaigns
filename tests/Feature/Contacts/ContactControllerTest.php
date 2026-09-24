<?php

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Enums\TeamRole;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\ContactList;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function contactTeamWithMember(?User $user = null): array
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

test('guests are redirected to login from contacts', function () {
    $team = Team::factory()->create();

    $this->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('non members cannot access contacts', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('members can list only their teams contacts', function () {
    [$team, $user] = contactTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'ada@example.com',
    ]);

    Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Ada Lovelace')
            ->where('contacts.data.0.email', 'ada@example.com')
            ->where('contacts.data.0.status', ContactStatus::NotContacted->value)
            ->where('contacts.data.0.status_label', 'NotContacted')
            ->has('lists')
            ->has('statuses'));
});

test('members can create a contact with identities and lists', function () {
    [$team, $user] = contactTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post(route('contacts.store', ['current_team' => $team->slug]), [
        'name' => 'Ada Lovelace',
        'email' => 'ADA@Example.com',
        'phone' => '+1 (555) 123-4567',
        'timezone' => 'UTC',
        'status' => ContactStatus::NotContacted->value,
        'source' => 'manual',
        'lists' => [$list->id],
    ]);

    $contact = Contact::query()->where('team_id', $team->id)->firstOrFail();

    $response
        ->assertRedirect(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Contact created.']);

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'team_id' => $team->id,
        'name' => 'Ada Lovelace',
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('contact_identities', [
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email->value,
        'normalized_value' => 'ada@example.com',
    ]);

    $this->assertDatabaseHas('contact_identities', [
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Phone->value,
        'normalized_value' => '15551234567',
    ]);

    $this->assertDatabaseHas('contact_list', [
        'contact_list_id' => $list->id,
        'contact_id' => $contact->id,
    ]);
});

test('email must be unique within a team', function () {
    [$team, $user] = contactTeamWithMember();

    $existing = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $existing->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'duplicate@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('contacts.store', ['current_team' => $team->slug]), [
            'name' => 'Another Contact',
            'email' => 'Duplicate@Example.com',
        ])
        ->assertSessionHasErrors('email');

    expect(Contact::query()->where('team_id', $team->id)->count())->toBe(1);
});

test('the same email can be used by another team', function () {
    $user = User::factory()->create();

    $firstTeam = Team::factory()->create();
    $firstTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $secondTeam = Team::factory()->create();
    $secondTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $existing = Contact::factory()->forTeam($firstTeam)->create();
    ContactIdentity::factory()->create([
        'team_id' => $firstTeam->id,
        'contact_id' => $existing->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'shared@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('contacts.store', ['current_team' => $secondTeam->slug]), [
            'name' => 'Second Team Contact',
            'email' => 'shared@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Contact::query()->where('team_id', $secondTeam->id)->count())->toBe(1);
});

test('members can view a contact', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'ada@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('contact.id', $contact->id)
            ->where('contact.name', 'Ada Lovelace')
            ->where('contact.email', 'ada@example.com')
            ->where('contact.status', ContactStatus::NotContacted->value)
            ->where('contact.do_not_contact', false)
            ->has('lists')
            ->has('recipients'));
});

test('a contact from another team is not found', function () {
    [$team, $user] = contactTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->get(route('contacts.show', ['current_team' => $team->slug, 'contact' => $otherContact]))
        ->assertNotFound();
});

test('members can update a contact', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'old@example.com',
    ]);

    $oldList = ContactList::factory()->create(['team_id' => $team->id]);
    $newList = ContactList::factory()->create(['team_id' => $team->id]);
    $contact->lists()->attach($oldList);

    $this->actingAs($user)
        ->patch(route('contacts.update', ['current_team' => $team->slug, 'contact' => $contact]), [
            'name' => 'Ada Updated',
            'email' => 'NEW@Example.com',
            'phone' => '+44 20 7946 0000',
            'timezone' => 'Europe/London',
            'status' => ContactStatus::Contacted->value,
            'lists' => [$newList->id],
        ])
        ->assertRedirect(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact]));

    $contact->refresh();

    expect($contact->name)->toBe('Ada Updated')
        ->and($contact->status)->toBe(ContactStatus::Contacted)
        ->and($contact->timezone)->toBe('Europe/London');

    expect($contact->identities()->forType(ContactIdentityType::Email)->count())->toBe(1)
        ->and($contact->fresh()->email())->toBe('new@example.com');

    expect($contact->identities()->forType(ContactIdentityType::Phone)->value('normalized_value'))->toBe('442079460000');

    expect($contact->lists()->pluck('contact_lists.id')->all())->toBe([$newList->id]);
});

test('updating a contact with its own email passes uniqueness', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'same@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('contacts.update', ['current_team' => $team->slug, 'contact' => $contact]), [
            'name' => 'Renamed',
            'email' => 'same@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('members can delete a contact', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->delete(route('contacts.destroy', ['current_team' => $team->slug, 'contact' => $contact]))
        ->assertRedirect(route('contacts.index', ['current_team' => $team->slug]));

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
});

test('a contact from another team cannot be updated or deleted', function () {
    [$team, $user] = contactTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('contacts.update', ['current_team' => $team->slug, 'contact' => $otherContact]), [
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('contacts.destroy', ['current_team' => $team->slug, 'contact' => $otherContact]))
        ->assertNotFound();
});

test('members can update a single contact cell', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);

    $route = route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]);

    $this->actingAs($user)
        ->patch($route, ['field' => 'name', 'value' => 'Ada Byron'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($contact->refresh()->name)->toBe('Ada Byron');

    $this->actingAs($user)
        ->patch($route, ['field' => 'timezone', 'value' => 'Europe/London'])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->timezone)->toBe('Europe/London');

    $this->actingAs($user)
        ->patch($route, ['field' => 'status', 'value' => ContactStatus::Contacted->value])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->status)->toBe(ContactStatus::Contacted);
});

test('members can update contact identity cells', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'old@example.com',
    ]);

    $route = route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]);

    $this->actingAs($user)
        ->patch($route, ['field' => 'email', 'value' => 'NEW@Example.com'])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->email())->toBe('new@example.com')
        ->and($contact->identities()->forType(ContactIdentityType::Email)->count())->toBe(1);

    $this->actingAs($user)
        ->patch($route, ['field' => 'phone', 'value' => '+1 (555) 123-4567'])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->phone())->toBe('15551234567')
        ->and($contact->identities()->forType(ContactIdentityType::Phone)->count())->toBe(1);
});

test('clearing a contact phone cell deletes the phone identity', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Phone,
        'normalized_value' => '15551234567',
    ]);

    $this->actingAs($user)
        ->patch(route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]), [
            'field' => 'phone',
            'value' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($contact->identities()->forType(ContactIdentityType::Phone)->count())->toBe(0);
});

test('a cell email must be unique within the team', function () {
    [$team, $user] = contactTeamWithMember();

    $existing = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $existing->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'duplicate@example.com',
    ]);

    $contact = Contact::factory()->forTeam($team)->create();
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'own@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]), [
            'field' => 'email',
            'value' => 'Duplicate@Example.com',
        ])
        ->assertSessionHasErrors('value');

    expect($contact->refresh()->email())->toBe('own@example.com');
});

test('a cell update requires a valid field', function () {
    [$team, $user] = contactTeamWithMember();

    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]), [
            'field' => 'nope',
            'value' => 'whatever',
        ])
        ->assertSessionHasErrors('field');
});

test('non members cannot update a contact cell', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.cell', ['current_team' => $team->slug, 'contact' => $contact]), [
            'field' => 'name',
            'value' => 'Hacked',
        ])
        ->assertForbidden();
});

test('a contact from another team cannot have a cell updated', function () {
    [$team, $user] = contactTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('contacts.cell', ['current_team' => $team->slug, 'contact' => $otherContact]), [
            'field' => 'name',
            'value' => 'Hacked',
        ])
        ->assertNotFound();
});

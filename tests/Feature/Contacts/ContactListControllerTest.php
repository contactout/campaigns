<?php

use App\Enums\TeamRole;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Team;
use App\Models\User;

/**
 * Create a team with the given user attached as a member.
 */
function contactListTeamWithMember(?User $user = null): array
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

test('non members cannot create a list', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->post(route('lists.store', ['current_team' => $team->slug]), ['name' => 'Sneaky'])
        ->assertForbidden();
});

test('members can create a contact list', function () {
    [$team, $user] = contactListTeamWithMember();

    $response = $this->actingAs($user)->post(route('lists.store', ['current_team' => $team->slug]), [
        'name' => 'Prospects',
    ]);

    $response
        ->assertRedirect(route('contacts.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'List created.']);

    $this->assertDatabaseHas('contact_lists', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Prospects',
        'is_default' => false,
    ]);
});

test('members can update a contact list', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id, 'name' => 'Old Name']);

    $this->actingAs($user)
        ->patch(route('lists.update', ['current_team' => $team->slug, 'list' => $list]), [
            'name' => 'New Name',
            'is_default' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('contact_lists', [
        'id' => $list->id,
        'name' => 'New Name',
        'is_default' => true,
    ]);
});

test('members can delete a contact list', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $contact = Contact::factory()->forTeam($team)->create();
    $list->contacts()->attach($contact);

    $this->actingAs($user)
        ->delete(route('lists.destroy', ['current_team' => $team->slug, 'list' => $list]))
        ->assertRedirect();

    $this->assertDatabaseMissing('contact_lists', ['id' => $list->id]);
    $this->assertDatabaseMissing('contact_list', ['contact_list_id' => $list->id]);
});

test('members can attach contacts to a list', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $first = Contact::factory()->forTeam($team)->create();
    $second = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('lists.contacts.attach', ['current_team' => $team->slug, 'list' => $list]), [
            'contacts' => [$first->id, $second->id],
        ])
        ->assertRedirect();

    expect($list->contacts()->count())->toBe(2);
});

test('attaching the same contact twice is idempotent', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $contact = Contact::factory()->forTeam($team)->create();

    $endpoint = route('lists.contacts.attach', ['current_team' => $team->slug, 'list' => $list]);

    $this->actingAs($user)->post($endpoint, ['contacts' => [$contact->id]])->assertRedirect();
    $this->actingAs($user)->post($endpoint, ['contacts' => [$contact->id]])->assertRedirect();

    expect($list->contacts()->count())->toBe(1);
});

test('contacts from another team cannot be attached', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $otherTeam = Team::factory()->create();
    $otherContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('lists.contacts.attach', ['current_team' => $team->slug, 'list' => $list]), [
            'contacts' => [$otherContact->id],
        ])
        ->assertSessionHasErrors('contacts.0');

    expect($list->contacts()->count())->toBe(0);
});

test('members can detach a contact from a list', function () {
    [$team, $user] = contactListTeamWithMember();

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $contact = Contact::factory()->forTeam($team)->create();
    $list->contacts()->attach($contact);

    $this->actingAs($user)
        ->delete(route('lists.contacts.detach', [
            'current_team' => $team->slug,
            'list' => $list,
            'contact' => $contact,
        ]))
        ->assertRedirect();

    $this->assertDatabaseMissing('contact_list', [
        'contact_list_id' => $list->id,
        'contact_id' => $contact->id,
    ]);
});

test('a list from another team is not found', function () {
    [$team, $user] = contactListTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherList = ContactList::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->patch(route('lists.update', ['current_team' => $team->slug, 'list' => $otherList]), [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();
});

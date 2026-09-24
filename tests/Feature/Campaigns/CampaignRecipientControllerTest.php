<?php

use App\Enums\RecipientStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\ContactList;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function campaignRecipientTeamWithMember(?User $user = null): array
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

test('members can add explicit contacts as recipients', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $first = Contact::factory()->forTeam($team)->create();
    $second = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'contacts' => [$first->id, $second->id],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recipients added.']);

    expect($campaign->recipients()->count())->toBe(2)
        ->and($campaign->recipients()->where('source', 'manual')->count())->toBe(2);
});

test('adding the same contact twice is idempotent', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $contact = Contact::factory()->forTeam($team)->create();

    $endpoint = route('campaigns.recipients.store', [
        'current_team' => $team->slug,
        'campaign' => $campaign,
    ]);

    $this->actingAs($user)->post($endpoint, ['contacts' => [$contact->id]])->assertRedirect();
    $this->actingAs($user)->post($endpoint, ['contacts' => [$contact->id]])->assertRedirect();

    expect($campaign->recipients()->count())->toBe(1);
});

test('members can add all contacts from a list as recipients', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $first = Contact::factory()->forTeam($team)->create();
    $second = Contact::factory()->forTeam($team)->create();
    $list->contacts()->attach([$first->id, $second->id]);

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'lists' => [$list->id],
        ])
        ->assertRedirect();

    expect($campaign->recipients()->count())->toBe(2)
        ->and($campaign->recipients()->where('source', 'list')->count())->toBe(2);
});

test('contacts from another team cannot be added as recipients', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $otherTeam = Team::factory()->create();
    $foreignContact = Contact::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'contacts' => [$foreignContact->id],
        ])
        ->assertSessionHasErrors('contacts.0');

    expect($campaign->recipients()->count())->toBe(0);
});

test('adding recipients requires at least one contact or list', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]))
        ->assertSessionHasErrors('contacts');

    expect($campaign->recipients()->count())->toBe(0);
});

test('members can remove a recipient', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $recipient = Recipient::factory()->for($campaign)->create();

    $this->actingAs($user)
        ->delete(route('campaigns.recipients.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'recipient' => $recipient,
        ]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recipient removed.']);

    $this->assertDatabaseMissing('recipients', ['id' => $recipient->id]);
});

test('members can bulk remove recipients', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $first = Recipient::factory()->for($campaign)->create();
    $second = Recipient::factory()->for($campaign)->create();
    $kept = Recipient::factory()->for($campaign)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.bulk-destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'recipients' => [$first->id, $second->id],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recipients removed.']);

    $this->assertDatabaseMissing('recipients', ['id' => $first->id]);
    $this->assertDatabaseMissing('recipients', ['id' => $second->id]);
    $this->assertDatabaseHas('recipients', ['id' => $kept->id]);
});

test('the campaign show page includes recipients and available contacts', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $recipientContact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $recipientContact->id,
        'normalized_value' => 'ada@example.com',
    ]);
    Recipient::factory()->for($campaign)->create(['contact_id' => $recipientContact->id]);

    $availableContact = Contact::factory()->forTeam($team)->create(['name' => 'Alan Turing']);
    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $availableContact->id,
        'normalized_value' => 'alan@example.com',
    ]);

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $list->contacts()->attach($availableContact);

    $this->actingAs($user)
        ->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.contact_id', $recipientContact->id)
            ->where('recipients.data.0.name', 'Ada Lovelace')
            ->where('recipients.data.0.email', 'ada@example.com')
            ->where('recipients.data.0.status', RecipientStatus::Active->value)
            ->where('recipients.data.0.status_label', 'Active')
            ->has('availableContacts', 1)
            ->where('availableContacts.0.id', $availableContact->id)
            ->where('availableContacts.0.name', 'Alan Turing')
            ->where('availableContacts.0.email', 'alan@example.com')
            ->has('lists', 1)
            ->where('lists.0.id', $list->id)
            ->where('lists.0.contacts_count', 1));
});

test('non members cannot add recipients', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->forTeam($team)->create();
    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), [
            'contacts' => [$contact->id],
        ])
        ->assertForbidden();
});

test('a campaign from another team is not found when adding recipients', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherCampaign = Campaign::factory()->forTeam($otherTeam)->create();
    $contact = Contact::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('campaigns.recipients.store', [
            'current_team' => $team->slug,
            'campaign' => $otherCampaign,
        ]), [
            'contacts' => [$contact->id],
        ])
        ->assertNotFound();
});

test('a recipient from another campaign is not found', function () {
    [$team, $user] = campaignRecipientTeamWithMember();

    $campaign = Campaign::factory()->forTeam($team)->create();
    $otherCampaign = Campaign::factory()->forTeam($team)->create();
    $foreignRecipient = Recipient::factory()->for($otherCampaign)->create();

    $this->actingAs($user)
        ->delete(route('campaigns.recipients.destroy', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
            'recipient' => $foreignRecipient,
        ]))
        ->assertNotFound();

    $this->assertDatabaseHas('recipients', ['id' => $foreignRecipient->id]);
});

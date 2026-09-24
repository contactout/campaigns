<?php

use App\Enums\PlaceholderType;
use App\Enums\TeamRole;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use App\Models\User;

/**
 * Create a team with the given user attached as a member.
 */
function placeholdersTeamWithMember(?User $user = null): array
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

test('non members cannot create a placeholder', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $template = EmailTemplate::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('placeholders.store', ['current_team' => $team->slug, 'template' => $template]), [
            'name' => 'first_name',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertForbidden();
});

test('members can create a placeholder on a template', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->post(route('placeholders.store', ['current_team' => $team->slug, 'template' => $template]), [
            'name' => 'first_name',
            'fallback' => 'there',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Placeholder created.']);

    $this->assertDatabaseHas('placeholders', [
        'team_id' => $team->id,
        'owner_type' => $template->getMorphClass(),
        'owner_id' => $template->id,
        'name' => 'first_name',
        'fallback' => 'there',
        'type' => PlaceholderType::Text->value,
    ]);
});

test('a placeholder name is unique within a template', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create();
    Placeholder::factory()->forTemplate($template)->create(['name' => 'first_name']);

    $this->actingAs($user)
        ->post(route('placeholders.store', ['current_team' => $team->slug, 'template' => $template]), [
            'name' => 'first_name',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertSessionHasErrors('name');

    expect($template->placeholders()->count())->toBe(1);
});

test('members can update a placeholder', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create();
    $placeholder = Placeholder::factory()->forTemplate($template)->create(['name' => 'first_name']);

    $this->actingAs($user)
        ->patch(route('placeholders.update', ['current_team' => $team->slug, 'placeholder' => $placeholder]), [
            'name' => 'company',
            'fallback' => 'Acme',
            'type' => PlaceholderType::Number->value,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Placeholder updated.']);

    $placeholder->refresh();

    expect($placeholder->name)->toBe('company')
        ->and($placeholder->fallback)->toBe('Acme')
        ->and($placeholder->type)->toBe(PlaceholderType::Number);
});

test('members can delete a placeholder', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create();
    $placeholder = Placeholder::factory()->forTemplate($template)->create();

    $this->actingAs($user)
        ->delete(route('placeholders.destroy', ['current_team' => $team->slug, 'placeholder' => $placeholder]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Placeholder deleted.']);

    $this->assertDatabaseMissing('placeholders', ['id' => $placeholder->id]);
});

test('a placeholder from another team is not found', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherTemplate = EmailTemplate::factory()->forTeam($otherTeam)->create();
    $otherPlaceholder = Placeholder::factory()->forTemplate($otherTemplate)->create();

    $this->actingAs($user)
        ->patch(route('placeholders.update', ['current_team' => $team->slug, 'placeholder' => $otherPlaceholder]), [
            'name' => 'hijacked',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('placeholders.destroy', ['current_team' => $team->slug, 'placeholder' => $otherPlaceholder]))
        ->assertNotFound();
});

test('a placeholder cannot be created on another teams template', function () {
    [$team, $user] = placeholdersTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherTemplate = EmailTemplate::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('placeholders.store', ['current_team' => $team->slug, 'template' => $otherTemplate]), [
            'name' => 'first_name',
            'type' => PlaceholderType::Text->value,
        ])
        ->assertNotFound();
});

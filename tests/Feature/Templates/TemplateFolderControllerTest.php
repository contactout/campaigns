<?php

use App\Enums\TeamRole;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TemplateFolder;
use App\Models\User;

/**
 * Create a team with the given user attached as a member.
 */
function templateFoldersTeamWithMember(?User $user = null): array
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

test('non members cannot create a folder', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->post(route('folders.store', ['current_team' => $team->slug]), ['name' => 'Sneaky'])
        ->assertForbidden();
});

test('members can create a folder', function () {
    [$team, $user] = templateFoldersTeamWithMember();

    $this->actingAs($user)
        ->post(route('folders.store', ['current_team' => $team->slug]), ['name' => 'Outreach'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Folder created.']);

    $this->assertDatabaseHas('template_folders', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Outreach',
    ]);
});

test('members can update a folder', function () {
    [$team, $user] = templateFoldersTeamWithMember();

    $folder = TemplateFolder::factory()->forTeam($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('folders.update', ['current_team' => $team->slug, 'folder' => $folder]), [
            'name' => 'New name',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Folder updated.']);

    expect($folder->fresh()->name)->toBe('New name');
});

test('members can delete a folder and filed templates are kept', function () {
    [$team, $user] = templateFoldersTeamWithMember();

    $folder = TemplateFolder::factory()->forTeam($team)->create();
    $template = EmailTemplate::factory()->forFolder($folder)->create();

    $this->actingAs($user)
        ->delete(route('folders.destroy', ['current_team' => $team->slug, 'folder' => $folder]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Folder deleted.']);

    $this->assertDatabaseMissing('template_folders', ['id' => $folder->id]);
    expect($template->fresh()->folder_id)->toBeNull();
});

test('a folder from another team is not found', function () {
    [$team, $user] = templateFoldersTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherFolder = TemplateFolder::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('folders.update', ['current_team' => $team->slug, 'folder' => $otherFolder]), [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('folders.destroy', ['current_team' => $team->slug, 'folder' => $otherFolder]))
        ->assertNotFound();
});

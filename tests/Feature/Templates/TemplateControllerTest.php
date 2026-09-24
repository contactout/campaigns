<?php

use App\Enums\PlaceholderType;
use App\Enums\TeamRole;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use App\Models\TemplateFolder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function templatesTeamWithMember(?User $user = null): array
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

test('guests are redirected to login from templates', function () {
    $team = Team::factory()->create();

    $this->get(route('templates.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('non members cannot access templates', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => $team->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('templates.store', ['current_team' => $team->slug]), [
            'name' => 'Nope',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

test('members can list only their teams templates and folders', function () {
    [$team, $user] = templatesTeamWithMember();

    $folder = TemplateFolder::factory()->forTeam($team)->create(['name' => 'Outreach']);
    EmailTemplate::factory()->forFolder($folder)->create(['name' => 'Welcome']);

    $otherTeam = Team::factory()->create();
    EmailTemplate::factory()->forTeam($otherTeam)->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/index')
            ->has('templates', 1)
            ->where('templates.0.name', 'Welcome')
            ->where('templates.0.folder_id', $folder->id)
            ->where('templates.0.is_draft', false)
            ->has('folders', 1)
            ->where('folders.0.name', 'Outreach')
            ->where('folders.0.templates_count', 1)
            ->where('can.create', true));
});

test('members can create a template', function () {
    [$team, $user] = templatesTeamWithMember();

    $folder = TemplateFolder::factory()->forTeam($team)->create();

    $response = $this->actingAs($user)->post(route('templates.store', ['current_team' => $team->slug]), [
        'name' => 'Welcome',
        'subject' => 'Hello there',
        'body' => 'Hi {{first_name}}',
        'folder_id' => $folder->id,
    ]);

    $template = EmailTemplate::query()->where('team_id', $team->id)->firstOrFail();

    $response
        ->assertRedirect(route('templates.show', ['current_team' => $team->slug, 'template' => $template]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Template created.']);

    expect($template->user_id)->toBe($user->id)
        ->and($template->subject)->toBe('Hello there')
        ->and($template->is_draft)->toBeFalse();

    $this->assertDatabaseHas('email_templates', [
        'id' => $template->id,
        'team_id' => $team->id,
        'folder_id' => $folder->id,
        'name' => 'Welcome',
    ]);
});

test('a template cannot be filed in another teams folder', function () {
    [$team, $user] = templatesTeamWithMember();

    $otherTeam = Team::factory()->create();
    $foreignFolder = TemplateFolder::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('templates.store', ['current_team' => $team->slug]), [
            'name' => 'Welcome',
            'body' => 'Body',
            'folder_id' => $foreignFolder->id,
        ])
        ->assertSessionHasErrors('folder_id');

    $this->assertDatabaseCount('email_templates', 0);
});

test('members can view a template with its placeholders', function () {
    [$team, $user] = templatesTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create(['name' => 'Welcome']);
    Placeholder::factory()->forTemplate($template)->create([
        'name' => 'first_name',
        'fallback' => 'there',
        'type' => PlaceholderType::Text,
    ]);

    $this->actingAs($user)
        ->get(route('templates.show', ['current_team' => $team->slug, 'template' => $template]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/show')
            ->where('template.id', $template->id)
            ->where('template.name', 'Welcome')
            ->has('placeholders', 1)
            ->where('placeholders.0.name', 'first_name')
            ->where('placeholders.0.fallback', 'there')
            ->where('placeholders.0.type', PlaceholderType::Text->value)
            ->where('placeholders.0.type_label', 'Text')
            ->has('folders')
            ->where('can.update', true)
            ->where('can.delete', true));
});

test('members can update a template', function () {
    [$team, $user] = templatesTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create(['name' => 'Old name']);
    $folder = TemplateFolder::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->patch(route('templates.update', ['current_team' => $team->slug, 'template' => $template]), [
            'name' => 'New name',
            'subject' => 'New subject',
            'body' => 'New body',
            'folder_id' => $folder->id,
            'is_draft' => true,
        ])
        ->assertRedirect(route('templates.show', ['current_team' => $team->slug, 'template' => $template]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Template updated.']);

    $template->refresh();

    expect($template->name)->toBe('New name')
        ->and($template->subject)->toBe('New subject')
        ->and($template->body)->toBe('New body')
        ->and($template->folder_id)->toBe($folder->id)
        ->and($template->is_draft)->toBeTrue();
});

test('members can delete a template and its placeholders', function () {
    [$team, $user] = templatesTeamWithMember();

    $template = EmailTemplate::factory()->forTeam($team)->create();
    Placeholder::factory()->forTemplate($template)->create();

    $this->actingAs($user)
        ->delete(route('templates.destroy', ['current_team' => $team->slug, 'template' => $template]))
        ->assertRedirect(route('templates.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Template deleted.']);

    $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    $this->assertDatabaseMissing('placeholders', ['owner_id' => $template->id]);
});

test('a template from another team is not found', function () {
    [$team, $user] = templatesTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherTemplate = EmailTemplate::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->get(route('templates.show', ['current_team' => $team->slug, 'template' => $otherTemplate]))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('templates.update', ['current_team' => $team->slug, 'template' => $otherTemplate]), [
            'name' => 'Hijacked',
            'body' => 'Body',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('templates.destroy', ['current_team' => $team->slug, 'template' => $otherTemplate]))
        ->assertNotFound();
});

<?php

use App\Enums\TeamRole;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function signaturesTeamWithMember(?User $user = null): array
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

test('non members cannot access signatures', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('signatures.index', ['current_team' => $team->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('signatures.store', ['current_team' => $team->slug]), [
            'name' => 'Sneaky',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

test('members can create and list signatures', function () {
    [$team, $user] = signaturesTeamWithMember();

    $this->actingAs($user)
        ->post(route('signatures.store', ['current_team' => $team->slug]), [
            'name' => 'Work',
            'body' => 'Best regards',
            'is_default' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Signature created.']);

    $this->assertDatabaseHas('signatures', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Work',
        'is_default' => true,
    ]);

    $otherTeam = Team::factory()->create();
    Signature::factory()->forTeam($otherTeam)->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('signatures.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('signatures/index')
            ->has('signatures', 1)
            ->where('signatures.0.name', 'Work')
            ->where('signatures.0.is_default', true)
            ->where('can.create', true));
});

test('members can update a signature', function () {
    [$team, $user] = signaturesTeamWithMember();

    $signature = Signature::factory()->forTeam($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('signatures.update', ['current_team' => $team->slug, 'signature' => $signature]), [
            'name' => 'New name',
            'body' => 'New body',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Signature updated.']);

    $signature->refresh();

    expect($signature->name)->toBe('New name')
        ->and($signature->body)->toBe('New body');
});

test('members can delete a signature', function () {
    [$team, $user] = signaturesTeamWithMember();

    $signature = Signature::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->delete(route('signatures.destroy', ['current_team' => $team->slug, 'signature' => $signature]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Signature deleted.']);

    $this->assertDatabaseMissing('signatures', ['id' => $signature->id]);
});

test('only one signature can be the team default', function () {
    [$team, $user] = signaturesTeamWithMember();

    $first = Signature::factory()->forTeam($team)->default()->create(['name' => 'First']);

    $this->actingAs($user)
        ->post(route('signatures.store', ['current_team' => $team->slug]), [
            'name' => 'Second',
            'body' => 'Body',
            'is_default' => true,
        ])
        ->assertRedirect();

    $second = Signature::query()->where('name', 'Second')->firstOrFail();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->is_default)->toBeTrue()
        ->and(Signature::query()->forTeam($team->id)->where('is_default', true)->count())->toBe(1);

    $this->actingAs($user)
        ->patch(route('signatures.update', ['current_team' => $team->slug, 'signature' => $first]), [
            'name' => 'First',
            'body' => 'Body',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($first->fresh()->is_default)->toBeTrue()
        ->and($second->fresh()->is_default)->toBeFalse();
});

test('a signature from another team is not found', function () {
    [$team, $user] = signaturesTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherSignature = Signature::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('signatures.update', ['current_team' => $team->slug, 'signature' => $otherSignature]), [
            'name' => 'Hijacked',
            'body' => 'Body',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('signatures.destroy', ['current_team' => $team->slug, 'signature' => $otherSignature]))
        ->assertNotFound();
});

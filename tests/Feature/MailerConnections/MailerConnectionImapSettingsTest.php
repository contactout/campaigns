<?php

use App\Enums\MailerConnectionStatus;
use App\Enums\TeamRole;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 *
 * @return array{0: Team, 1: User}
 */
function mailerConnectionImapTeamWithMember(?User $user = null): array
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

test('imap settings are validated and persisted on create', function () {
    [$team, $user] = mailerConnectionImapTeamWithMember();

    $this->actingAs($user)
        ->post(route('mailer-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Primary',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'imap-user',
            'imap_password' => 'imap-secret',
        ])
        ->assertRedirect();

    $connection = MailerConnection::query()->where('team_id', $team->id)->firstOrFail();

    expect($connection->smtp_setting)->toMatchArray([
        'imap_host' => 'imap.example.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'imap-user',
        'imap_password' => 'imap-secret',
    ]);
});

test('an invalid imap encryption value is rejected', function () {
    [$team, $user] = mailerConnectionImapTeamWithMember();

    $this->actingAs($user)
        ->post(route('mailer-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Primary',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
            'imap_encryption' => 'pigeon',
        ])
        ->assertSessionHasErrors('imap_encryption');
});

test('updating a mailer connection preserves a blank imap password', function () {
    [$team, $user] = mailerConnectionImapTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'old-user',
            'password' => 'old-secret',
            'encryption' => 'tls',
            'from_email' => 'old@example.com',
            'from_name' => 'Old',
            'imap_host' => 'old-imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'old-imap-user',
            'imap_password' => 'old-imap-secret',
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('mailer-connections.update', [
            'current_team' => $team->slug,
            'mailerConnection' => $connection,
        ]), [
            'name' => 'Renamed',
            'host' => 'new.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'from_email' => 'new@example.com',
            'imap_host' => 'new-imap.example.com',
            'imap_port' => 143,
            'imap_encryption' => 'starttls',
            'imap_username' => 'new-imap-user',
            'imap_password' => '',
        ])
        ->assertRedirect();

    $connection->refresh();

    expect($connection->smtp_setting['imap_host'])->toBe('new-imap.example.com')
        ->and($connection->smtp_setting['imap_port'])->toBe(143)
        ->and($connection->smtp_setting['imap_encryption'])->toBe('starttls')
        ->and($connection->smtp_setting['imap_username'])->toBe('new-imap-user')
        ->and($connection->smtp_setting['imap_password'])->toBe('old-imap-secret');
});

test('the connection summary exposes imap fields but never passwords', function () {
    [$team, $user] = mailerConnectionImapTeamWithMember();

    MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'smtp-secret',
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
            'from_name' => 'Hello',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'imap-user',
            'imap_password' => 'imap-secret',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->has('connections', 1)
            ->where('connections.0.imap_host', 'imap.example.com')
            ->where('connections.0.imap_port', 993)
            ->where('connections.0.imap_encryption', 'ssl')
            ->where('connections.0.imap_username', 'imap-user')
            ->missing('connections.0.password')
            ->missing('connections.0.imap_password')
            ->missing('connections.0.smtp_setting'));
});

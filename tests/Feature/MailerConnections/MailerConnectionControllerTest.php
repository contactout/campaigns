<?php

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Enums\TeamRole;
use App\Models\MailerConnection;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\SmtpConnectionVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Create a team with the given user attached as a member.
 */
function mailerConnectionTeamWithMember(?User $user = null): array
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

test('guests are redirected to login from mailer connections', function () {
    $team = Team::factory()->create();

    $this->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('non members cannot access mailer connections', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('mailer-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Sneaky',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
        ])
        ->assertForbidden();
});

test('members can list only their teams mailer connections', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    MailerConnection::factory()->forTeam($team)->create([
        'name' => 'Primary',
        'status' => MailerConnectionStatus::Active,
    ]);

    MailerConnection::factory()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->has('connections', 1)
            ->where('connections.0.name', 'Primary')
            ->where('connections.0.mailer_type', MailerType::Smtp->value)
            ->where('connections.0.mailer_type_label', 'SMTP')
            ->where('connections.0.host', 'smtp.example.com')
            ->where('connections.0.port', 587)
            ->where('connections.0.status', MailerConnectionStatus::Active->value)
            ->where('connections.0.status_label', 'Active')
            ->where('can.create', true)
            ->missing('connections.0.smtp_setting')
            ->missing('connections.0.password'));
});

test('members can create a mailer connection with encrypted settings', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $this->actingAs($user)
        ->post(route('mailer-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Primary',
            'host' => 'smtp.mailtrap.io',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'super-secret',
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
            'from_name' => 'Hello',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Connection created.']);

    $connection = MailerConnection::query()->where('team_id', $team->id)->firstOrFail();

    expect($connection->name)->toBe('Primary')
        ->and($connection->user_id)->toBe($user->id)
        ->and($connection->mailer_type)->toBe(MailerType::Smtp)
        ->and($connection->status)->toBe(MailerConnectionStatus::Pending)
        ->and($connection->smtp_setting)->toMatchArray([
            'host' => 'smtp.mailtrap.io',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'super-secret',
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
            'from_name' => 'Hello',
        ]);

    $raw = (string) DB::table('mailer_connections')->where('id', $connection->id)->value('smtp_setting');

    expect($raw)->not->toContain('super-secret')
        ->and($raw)->not->toContain('smtp.mailtrap.io');
});

test('updating a mailer connection merges settings and preserves a blank password', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'smtp_setting' => [
            'host' => 'old.example.com',
            'port' => 587,
            'username' => 'old-user',
            'password' => 'old-secret',
            'encryption' => 'tls',
            'from_email' => 'old@example.com',
            'from_name' => 'Old',
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
            'username' => 'new-user',
            'password' => '',
            'encryption' => 'ssl',
            'from_email' => 'new@example.com',
            'from_name' => 'New',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Connection updated.']);

    $connection->refresh();

    expect($connection->name)->toBe('Renamed')
        ->and($connection->smtp_setting['host'])->toBe('new.example.com')
        ->and($connection->smtp_setting['port'])->toBe(465)
        ->and($connection->smtp_setting['username'])->toBe('new-user')
        ->and($connection->smtp_setting['encryption'])->toBe('ssl')
        ->and($connection->smtp_setting['password'])->toBe('old-secret');
});

test('members can set and clear the sending caps of a mailer connection', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $payload = [
        'name' => 'Primary',
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'from_email' => 'hello@example.com',
    ];

    $this->actingAs($user)
        ->post(route('mailer-connections.store', ['current_team' => $team->slug]), [
            ...$payload,
            'sending_limit' => '50',
            'min_seconds_between_sends' => '90',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $connection = MailerConnection::query()->where('team_id', $team->id)->firstOrFail();

    expect($connection->sending_limit)->toBe(50)
        ->and($connection->min_seconds_between_sends)->toBe(90);

    $route = route('mailer-connections.update', ['current_team' => $team->slug, 'mailerConnection' => $connection]);

    $this->actingAs($user)
        ->patch($route, [...$payload, 'sending_limit' => 0, 'min_seconds_between_sends' => -1])
        ->assertSessionHasErrors(['sending_limit', 'min_seconds_between_sends']);

    $this->actingAs($user)
        ->patch($route, [...$payload, 'sending_limit' => 4294967296, 'min_seconds_between_sends' => 4294967296])
        ->assertSessionHasErrors(['sending_limit', 'min_seconds_between_sends']);

    $this->actingAs($user)
        ->patch($route, [...$payload, 'sending_limit' => '', 'min_seconds_between_sends' => ''])
        ->assertSessionHasNoErrors();

    $connection->refresh();

    expect($connection->sending_limit)->toBeNull()
        ->and($connection->min_seconds_between_sends)->toBeNull();
});

test('members can delete a mailer connection', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create();

    $this->actingAs($user)
        ->delete(route('mailer-connections.destroy', [
            'current_team' => $team->slug,
            'mailerConnection' => $connection,
        ]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Connection deleted.']);

    $this->assertDatabaseMissing('mailer_connections', ['id' => $connection->id]);
});

test('a mailer connection from another team is not found', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $otherTeam = Team::factory()->create();
    $otherConnection = MailerConnection::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('mailer-connections.update', [
            'current_team' => $team->slug,
            'mailerConnection' => $otherConnection,
        ]), [
            'name' => 'Hijacked',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'hello@example.com',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('mailer-connections.destroy', [
            'current_team' => $team->slug,
            'mailerConnection' => $otherConnection,
        ]))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('mailer-connections.verify', [
            'current_team' => $team->slug,
            'mailerConnection' => $otherConnection,
        ]))
        ->assertNotFound();
});

test('the connections page lists the team signatures and each connection signature', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $signature = Signature::factory()->forTeam($team)->create(['name' => 'Alex']);
    Signature::factory()->forTeam($team)->default()->create(['name' => 'Team']);
    Signature::factory()->create(['name' => 'Hidden']);

    MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connections.0.signature_id', $signature->id)
            ->has('signatures', 2)
            ->where('signatures.0.name', 'Team')
            ->where('signatures.0.is_default', true)
            ->where('signatures.1.name', 'Alex')
            ->missing('signatures.0.body'));
});

test('members can assign and clear a connection signature of any mailer type', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $signature = Signature::factory()->forTeam($team)->create();
    $connection = MailerConnection::factory()->forTeam($team)->create(['mailer_type' => MailerType::Gmail]);

    $route = route('mailer-connections.signature.update', [
        'current_team' => $team->slug,
        'mailerConnection' => $connection,
    ]);

    $this->actingAs($user)
        ->put($route, ['signature_id' => $signature->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Signature updated.']);

    expect($connection->fresh()->signature_id)->toBe($signature->id);

    $this->actingAs($user)
        ->put($route, ['signature_id' => null])
        ->assertRedirect();

    expect($connection->fresh()->signature_id)->toBeNull();
});

test('a connection cannot use another team signature', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create();
    $foreign = Signature::factory()->create();

    $this->actingAs($user)
        ->put(route('mailer-connections.signature.update', [
            'current_team' => $team->slug,
            'mailerConnection' => $connection,
        ]), ['signature_id' => $foreign->id])
        ->assertSessionHasErrors('signature_id');

    expect($connection->fresh()->signature_id)->toBeNull();
});

test('another team connection signature cannot be changed', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $signature = Signature::factory()->forTeam($team)->create();
    $otherConnection = MailerConnection::factory()->create();

    $this->actingAs($user)
        ->put(route('mailer-connections.signature.update', [
            'current_team' => $team->slug,
            'mailerConnection' => $otherConnection,
        ]), ['signature_id' => $signature->id])
        ->assertNotFound();

    expect($otherConnection->fresh()->signature_id)->toBeNull();
});

test('deleting a signature clears it from the connections using it', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create();
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $signature->delete();

    expect($connection->fresh()->signature_id)->toBeNull();
});

test('verifying a reachable mailer connection marks it active and clears the error', function (MailerConnectionStatus $status) {
    [$team, $user] = mailerConnectionTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => $status,
        'exception_type' => RuntimeException::class,
        'exception_data' => ['message' => 'Previous failure'],
        'threw_at' => now()->subDay(),
    ]);

    $this->mock(SmtpConnectionVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once();
    });

    $this->actingAs($user)
        ->post(route('mailer-connections.verify', [
            'current_team' => $team->slug,
            'mailerConnection' => $connection,
        ]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Connection verified.']);

    $connection->refresh();

    expect($connection->status)->toBe(MailerConnectionStatus::Active)
        ->and($connection->exception_type)->toBeNull()
        ->and($connection->exception_data)->toBeNull()
        ->and($connection->threw_at)->toBeNull();
})->with([MailerConnectionStatus::Pending, MailerConnectionStatus::Deactivated]);

test('verifying an unreachable mailer connection deactivates it and stores the error', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Pending,
    ]);

    $this->mock(SmtpConnectionVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once()->andThrow(new RuntimeException('Connection refused'));
    });

    $this->actingAs($user)
        ->post(route('mailer-connections.verify', [
            'current_team' => $team->slug,
            'mailerConnection' => $connection,
        ]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Connection refused']);

    $connection->refresh();

    expect($connection->status)->toBe(MailerConnectionStatus::Deactivated)
        ->and($connection->exception_type)->toBe(RuntimeException::class)
        ->and($connection->exception_data)->toBe(['message' => 'Connection refused'])
        ->and($connection->threw_at)->not->toBeNull();
});

test('sent today reads the counter the daily cap enforces inside its refresh window', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    // Windows still open: the counter is today's, so the meter and the cap agree.
    $inside = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'A inside window',
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => 400,
        'sent_count' => 142,
        'sending_limit_refreshed_at' => CarbonImmutable::now('UTC')->addHours(8),
    ]);

    $quiet = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'B quiet',
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => 400,
        'sent_count' => 0,
        'sending_limit_refreshed_at' => CarbonImmutable::now('UTC')->addHours(8),
    ]);

    // The window passed: `sent_count` is still yesterday's, so it must not show.
    $stale = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'C stale window',
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => 400,
        'sent_count' => 99,
        'sending_limit_refreshed_at' => CarbonImmutable::now('UTC')->subDay(),
    ]);

    // A deleted campaign takes its `campaign_emails` rows with it, but not the
    // counter the cap counts; the at-cap connection still shows no room left.
    $atCap = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'D at cap',
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => 400,
        'sent_count' => 400,
        'sending_limit_refreshed_at' => CarbonImmutable::now('UTC')->addHours(8),
    ]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->has('connections', 4)
            ->where('connections.0.id', $inside->id)
            ->where('connections.0.sent_today', 142)
            ->where('connections.1.id', $quiet->id)
            ->where('connections.1.sent_today', 0)
            ->where('connections.2.id', $stale->id)
            ->where('connections.2.sent_today', 0)
            ->where('connections.3.id', $atCap->id)
            ->where('connections.3.sent_today', 400));
});

test('each connection exposes the derived display status and needs attention flag', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    $verified = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'A verified',
        'status' => MailerConnectionStatus::Active,
    ]);

    $failed = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'B failed',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => RuntimeException::class,
        'exception_data' => ['message' => '535 Authentication failed.'],
        'threw_at' => now(),
    ]);

    $pending = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'C pending',
        'status' => MailerConnectionStatus::Pending,
    ]);

    $deactivated = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'D deactivated',
        'status' => MailerConnectionStatus::Deactivated,
    ]);

    $disconnected = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'E disconnected',
        'status' => MailerConnectionStatus::Disconnected,
    ]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->has('connections', 5)
            ->where('connections.0.id', $verified->id)
            ->where('connections.0.display_status', 'Verified')
            ->where('connections.0.display_status_label', 'Verified')
            ->where('connections.0.needs_attention', false)
            ->where('connections.0.last_error_message', null)
            ->where('connections.1.id', $failed->id)
            ->where('connections.1.display_status', 'Failed')
            ->where('connections.1.needs_attention', true)
            ->where('connections.1.last_error_message', '535 Authentication failed.')
            ->where('connections.2.id', $pending->id)
            ->where('connections.2.display_status', 'Pending')
            ->where('connections.2.needs_attention', true)
            ->where('connections.3.id', $deactivated->id)
            ->where('connections.3.display_status', 'Deactivated')
            ->where('connections.3.needs_attention', true)
            ->where('connections.4.id', $disconnected->id)
            ->where('connections.4.display_status', 'Disconnected')
            ->where('connections.4.needs_attention', true));
});

test('a connection that has never refreshed its sending window reports zero sent today', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    // No window has rolled yet, so nothing can be inside one. The payload says
    // 0 and the page renders "—" for the limit rather than inventing a usage.
    $connection = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'A never rolled',
        'status' => MailerConnectionStatus::Active,
        'sent_count' => 250,
        'sending_limit' => null,
        'sending_limit_refreshed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->where('connections.0.id', $connection->id)
            ->where('connections.0.sent_today', 0)
            ->where('connections.0.sending_limit', null));
});

test('the stored failure message is trimmed and capped before it leaves the server', function () {
    [$team, $user] = mailerConnectionTeamWithMember();

    // A Guzzle message appends the provider's response body after "response:".
    $providerEcho = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'A provider echo',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => RuntimeException::class,
        'exception_data' => [
            'message' => 'Client error: `POST https://oauth2.example/token` resulted in a `401 Unauthorized` response: {"error":"invalid_client","error_description":"Unauthorized"}',
        ],
        'threw_at' => now(),
    ]);

    $long = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'B long message',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => RuntimeException::class,
        'exception_data' => ['message' => str_repeat('S', 900)],
        'threw_at' => now(),
    ]);

    $empty = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'C empty',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => RuntimeException::class,
        'exception_data' => ['message' => '   '],
        'threw_at' => now(),
    ]);

    $notAString = MailerConnection::factory()->forTeam($team)->create([
        'name' => 'D array',
        'status' => MailerConnectionStatus::Active,
        'exception_type' => RuntimeException::class,
        'exception_data' => ['message' => ['not', 'a', 'string']],
        'threw_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->has('connections', 4)
            ->where('connections.0.id', $providerEcho->id)
            // The envelope is dropped and the provider's own reason is kept: the
            // codes are what separate a revoked grant from a bad secret.
            ->where('connections.0.last_error_message', 'Client error: `POST https://oauth2.example/token` resulted in a `401 Unauthorized`; invalid_client: Unauthorized')
            ->where('connections.1.id', $long->id)
            ->where('connections.1.last_error_message', str_repeat('S', 300).'...')
            ->where('connections.2.id', $empty->id)
            ->where('connections.2.last_error_message', null)
            ->where('connections.3.id', $notAString->id)
            ->where('connections.3.last_error_message', null));
});

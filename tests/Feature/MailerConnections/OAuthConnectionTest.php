<?php

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Enums\TeamRole;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 *
 * @return array{0: Team, 1: User}
 */
function oauthMailerTeamWithMember(?User $user = null): array
{
    $user ??= User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return [$team, $user];
}

/**
 * Configure Google OAuth credentials for the test.
 */
function configureGoogleOAuth(): void
{
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => 'http://localhost/oauth/google/callback',
        'services.google.scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.readonly',
        ],
    ]);
}

/**
 * Configure Microsoft OAuth credentials for the test.
 */
function configureMicrosoftOAuth(): void
{
    config([
        'services.microsoft.client_id' => 'ms-client-id',
        'services.microsoft.client_secret' => 'ms-client-secret',
        'services.microsoft.redirect' => 'http://localhost/oauth/microsoft/callback',
        'services.microsoft.tenant' => 'common',
        'services.microsoft.scopes' => [
            'openid',
            'offline_access',
            'profile',
            'User.Read',
            'Mail.Send',
            'Mail.Read',
        ],
    ]);
}

beforeEach(function (): void {
    $this->withoutVite();

    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('guests are redirected to login from the oauth redirect', function () {
    configureGoogleOAuth();

    $team = Team::factory()->create();

    $this->get(route('mailer-connections.oauth.redirect', [
        'current_team' => $team->slug,
        'provider' => 'gmail',
    ]))->assertRedirect(route('login'));
});

test('oauth redirect returns 404 when the provider is not configured', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    [$team, $user] = oauthMailerTeamWithMember();

    $this->actingAs($user)
        ->get(route('mailer-connections.oauth.redirect', [
            'current_team' => $team->slug,
            'provider' => 'gmail',
        ]))
        ->assertNotFound();
});

test('oauth redirect sends members to google authorization', function () {
    configureGoogleOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    $response = $this->actingAs($user)
        ->get(route('mailer-connections.oauth.redirect', [
            'current_team' => $team->slug,
            'provider' => 'gmail',
        ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://accounts.google.com/o/oauth2/v2/auth');
    expect(session('mailer_oauth'))->toMatchArray([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'provider' => 'gmail',
    ])->and(session('mailer_oauth.state'))->not->toBeEmpty();
});

test('oauth redirect sends members to microsoft authorization', function () {
    configureMicrosoftOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    $response = $this->actingAs($user)
        ->get(route('mailer-connections.oauth.redirect', [
            'current_team' => $team->slug,
            'provider' => 'outlook',
        ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
});

test('google callback creates an active gmail connection', function () {
    configureGoogleOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
            'scope' => 'email profile',
        ]),
        'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
            'email' => 'ada@gmail.com',
            'name' => 'Ada Lovelace',
            'sub' => 'google-sub-1',
        ]),
    ]);

    $this->actingAs($user)
        ->withSession([
            'mailer_oauth' => [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'provider' => 'gmail',
                'state' => 'valid-state',
            ],
        ])
        ->get(route('mailer-connections.oauth.google.callback', [
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]))
        ->assertRedirect(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Mailer connection connected.']);

    $connection = MailerConnection::query()->where('team_id', $team->id)->first();

    expect($connection)->not->toBeNull()
        ->and($connection->mailer_type)->toBe(MailerType::Gmail)
        ->and($connection->status)->toBe(MailerConnectionStatus::Active)
        ->and($connection->name)->toBe('Ada Lovelace')
        ->and($connection->smtp_setting['email'])->toBe('ada@gmail.com')
        ->and($connection->smtp_setting['access_token'])->toBe('access-1')
        ->and($connection->smtp_setting['refresh_token'])->toBe('refresh-1');

    $this->actingAs($user)
        ->get(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mailer-connections/index')
            ->where('connections.0.from_email', 'ada@gmail.com')
            ->where('connections.0.from_name', 'Ada Lovelace')
            ->missing('connections.0.access_token')
            ->missing('connections.0.refresh_token')
            ->where('oauth.gmail', true));
});

test('google callback reconnects the same email and updates tokens', function () {
    configureGoogleOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    $existing = MailerConnection::factory()->forTeam($team)->create([
        'mailer_type' => MailerType::Gmail,
        'name' => 'Old Name',
        'status' => MailerConnectionStatus::Deactivated,
        'smtp_setting' => [
            'email' => 'ada@gmail.com',
            'from_email' => 'ada@gmail.com',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subHour()->toIso8601String(),
            'name' => 'Old Name',
            'from_name' => 'Old Name',
            'provider_user_id' => 'google-sub-1',
            'scope' => 'email',
        ],
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-2',
            'refresh_token' => 'refresh-2',
            'expires_in' => 3600,
            'scope' => 'email profile',
        ]),
        'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
            'email' => 'ada@gmail.com',
            'name' => 'Ada Lovelace',
            'sub' => 'google-sub-1',
        ]),
    ]);

    $this->actingAs($user)
        ->withSession([
            'mailer_oauth' => [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'provider' => 'gmail',
                'state' => 'valid-state',
            ],
        ])
        ->get(route('mailer-connections.oauth.google.callback', [
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]))
        ->assertRedirect(route('mailer-connections.index', ['current_team' => $team->slug]));

    expect(MailerConnection::query()->where('team_id', $team->id)->count())->toBe(1);

    $existing->refresh();

    expect($existing->status)->toBe(MailerConnectionStatus::Active)
        ->and($existing->name)->toBe('Ada Lovelace')
        ->and($existing->smtp_setting['access_token'])->toBe('access-2')
        ->and($existing->smtp_setting['refresh_token'])->toBe('refresh-2')
        ->and($existing->exception_type)->toBeNull();
});

test('microsoft callback creates an active outlook connection', function () {
    configureMicrosoftOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_in' => 3600,
            'scope' => 'Mail.Send',
        ]),
        'graph.microsoft.com/v1.0/me' => Http::response([
            'id' => 'ms-user-1',
            'mail' => 'ada@outlook.com',
            'displayName' => 'Ada Lovelace',
        ]),
    ]);

    $this->actingAs($user)
        ->withSession([
            'mailer_oauth' => [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'provider' => 'outlook',
                'state' => 'ms-state',
            ],
        ])
        ->get(route('mailer-connections.oauth.microsoft.callback', [
            'code' => 'auth-code',
            'state' => 'ms-state',
        ]))
        ->assertRedirect(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Mailer connection connected.']);

    $connection = MailerConnection::query()->where('team_id', $team->id)->first();

    expect($connection)->not->toBeNull()
        ->and($connection->mailer_type)->toBe(MailerType::Outlook)
        ->and($connection->status)->toBe(MailerConnectionStatus::Active)
        ->and($connection->smtp_setting['email'])->toBe('ada@outlook.com')
        ->and($connection->smtp_setting['access_token'])->toBe('ms-access');
});

test('oauth callback rejects a mismatched state', function () {
    configureGoogleOAuth();

    [$team, $user] = oauthMailerTeamWithMember();

    Http::fake();

    $this->actingAs($user)
        ->withSession([
            'mailer_oauth' => [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'provider' => 'gmail',
                'state' => 'expected-state',
            ],
        ])
        ->get(route('mailer-connections.oauth.google.callback', [
            'code' => 'auth-code',
            'state' => 'wrong-state',
        ]))
        ->assertRedirect(route('mailer-connections.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'OAuth state validation failed.']);

    expect(MailerConnection::query()->where('team_id', $team->id)->count())->toBe(0);
    Http::assertNothingSent();
});

<?php

use App\Models\TeamInvitation;
use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

test('the first user can register when registration is disabled', function () {
    config(['app.registration_enabled' => false]);

    $this->get(route('register'))->assertOk();

    $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    expect(User::count())->toBe(1);
});

test('additional users are blocked when registration is disabled', function () {
    config(['app.registration_enabled' => false]);
    User::factory()->create();

    $this->get(route('register'))->assertNotFound();

    $this->post(route('register.store'), registrationPayload())->assertForbidden();

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('additional users can register when registration is enabled', function () {
    config(['app.registration_enabled' => true]);
    User::factory()->create();

    $this->get(route('register'))->assertOk();

    $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    expect(User::count())->toBe(2);
});

test('a valid invitation allows registration when registration is disabled', function () {
    config(['app.registration_enabled' => false]);
    $invitation = TeamInvitation::factory()->create(['email' => 'new@example.com']);

    $this->get(route('register', ['invitation' => $invitation->code]))->assertOk();

    $this->post(route('register.store'), registrationPayload(['invitation' => $invitation->code]));

    $this->assertAuthenticated();
});

test('an invitation for a different email does not allow registration', function () {
    config(['app.registration_enabled' => false]);
    $invitation = TeamInvitation::factory()->create(['email' => 'someone-else@example.com']);

    $this->post(route('register.store'), registrationPayload(['invitation' => $invitation->code]))
        ->assertForbidden();

    $this->assertGuest();
});

test('invalid, expired or accepted invitations do not allow registration', function (?callable $makeCode) {
    config(['app.registration_enabled' => false]);
    User::factory()->create();

    $code = $makeCode ? $makeCode() : 'not-a-real-code';

    $this->get(route('register', ['invitation' => $code]))->assertNotFound();

    $this->post(route('register.store'), registrationPayload(['invitation' => $code]))->assertForbidden();

    $this->assertGuest();
})->with([
    'unknown code' => [null],
    'expired' => [fn () => TeamInvitation::factory()->expired()->create(['email' => 'new@example.com'])->code],
    'accepted' => [fn () => TeamInvitation::factory()->accepted()->create(['email' => 'new@example.com'])->code],
]);

test('canRegister is shared with the frontend', function () {
    config(['app.registration_enabled' => false]);
    User::factory()->create();

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', false));

    config(['app.registration_enabled' => true]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', true));
});

<?php

use App\Models\User;

test('it marks an unverified user as verified', function () {
    $user = User::factory()->unverified()->create();

    $this->artisan('campaigns:verify-user', ['email' => $user->email])
        ->expectsOutputToContain('Marked')
        ->assertSuccessful();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('it succeeds for an already verified user', function () {
    $user = User::factory()->create();

    $this->artisan('campaigns:verify-user', ['email' => $user->email])
        ->expectsOutputToContain('already verified')
        ->assertSuccessful();
});

test('it fails for an unknown email', function () {
    $this->artisan('campaigns:verify-user', ['email' => 'missing@example.com'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();
});

<?php

use App\Enums\MailerConnectionDisplayStatus;
use App\Enums\MailerConnectionStatus;

test('an active connection with no exception is verified', function () {
    expect(MailerConnectionDisplayStatus::derive(MailerConnectionStatus::Active, false))
        ->toBe(MailerConnectionDisplayStatus::Verified);
});

test('an exception fails the connection whatever its stored status is', function () {
    foreach (MailerConnectionStatus::cases() as $status) {
        expect(MailerConnectionDisplayStatus::derive($status, true))
            ->toBe(MailerConnectionDisplayStatus::Failed);
    }
});

test('a stored status without an exception maps to its own display status', function () {
    expect(MailerConnectionDisplayStatus::derive(MailerConnectionStatus::Pending, false))
        ->toBe(MailerConnectionDisplayStatus::Pending)
        ->and(MailerConnectionDisplayStatus::derive(MailerConnectionStatus::Deactivated, false))
        ->toBe(MailerConnectionDisplayStatus::Deactivated)
        ->and(MailerConnectionDisplayStatus::derive(MailerConnectionStatus::Disconnected, false))
        ->toBe(MailerConnectionDisplayStatus::Disconnected);
});

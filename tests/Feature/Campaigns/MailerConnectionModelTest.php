<?php

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\Campaign;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;

test('mailer connection casts mailer type and status', function () {
    $connection = MailerConnection::factory()->create([
        'mailer_type' => MailerType::Gmail,
        'status' => MailerConnectionStatus::Active,
    ]);

    $fresh = $connection->fresh();

    expect($fresh->mailer_type)->toBe(MailerType::Gmail)
        ->and($fresh->status)->toBe(MailerConnectionStatus::Active);
});

test('mailer connection casts smtp settings to an array', function () {
    $connection = MailerConnection::factory()->create([
        'smtp_setting' => ['host' => 'mailpit', 'port' => 1025],
    ]);

    expect($connection->fresh()->smtp_setting)->toBe(['host' => 'mailpit', 'port' => 1025]);
});

test('mailer connection has many campaigns', function () {
    $connection = MailerConnection::factory()->create();

    Campaign::factory()->count(2)->for($connection, 'mailerConnection')->create();

    expect($connection->campaigns)->toHaveCount(2);
});

test('mailer connection belongs to a team and a user', function () {
    $connection = MailerConnection::factory()->create();

    expect($connection->team)->toBeInstanceOf(Team::class)
        ->and($connection->user)->toBeInstanceOf(User::class);
});

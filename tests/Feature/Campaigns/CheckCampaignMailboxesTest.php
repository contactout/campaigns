<?php

use App\Enums\MailerConnectionStatus;
use App\Jobs\Campaigns\CheckConnectionMailbox;
use App\Models\MailerConnection;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

test('the command dispatches a check for each eligible connection', function () {
    Queue::fake();

    $team = Team::factory()->create();

    $eligible = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'sender@example.com',
            'imap_host' => 'imap.example.com',
        ],
    ]);

    // Active but without an IMAP host.
    MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
    ]);

    // Has an IMAP host but is not active.
    MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Deactivated,
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'sender@example.com',
            'imap_host' => 'imap.example.com',
        ],
    ]);

    $this->artisan('campaigns:check-mailboxes')
        ->expectsOutputToContain('Dispatched 1 mailbox check(s).')
        ->assertSuccessful();

    Queue::assertPushed(CheckConnectionMailbox::class, 1);
    Queue::assertPushed(
        CheckConnectionMailbox::class,
        fn (CheckConnectionMailbox $job): bool => $job->mailerConnection->is($eligible),
    );
});

test('the command dispatches nothing when no connection is eligible', function () {
    Queue::fake();

    MailerConnection::factory()->create(['status' => MailerConnectionStatus::Active]);

    $this->artisan('campaigns:check-mailboxes')
        ->expectsOutputToContain('Dispatched 0 mailbox check(s).')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

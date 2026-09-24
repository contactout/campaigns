<?php

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Enums\ContactIdentityType;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Jobs\Campaigns\CheckConnectionMailbox;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Tests\Support\FakeMailboxReader;

/**
 * Build an active campaign with a connection, contact, recipient and sent email.
 *
 * @return array<string, mixed>
 */
function mmosMailboxFixture(): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'secret',
            'encryption' => 'tls',
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'mailer-user',
            'imap_password' => 'imap-secret',
        ],
    ]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::now(),
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'subject' => 'Hello',
        'body' => '<p>Hi</p>',
        'day' => 0,
        'time' => '09:00:00',
    ]);

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'ada@example.com',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => RecipientStatus::Active,
    ]);

    $email = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $recipient->id,
        'mailer_connection_id' => $connection->id,
        'message_id' => 'sent-message-id@example.com',
        'status' => EmailStatus::Sent,
        'dispatched_at' => CarbonImmutable::now(),
    ]);

    return compact('team', 'connection', 'campaign', 'step', 'contact', 'recipient', 'email');
}

test('the job processes messages and records the last checked time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();

    $reader = new FakeMailboxReader([
        new InboundMessage(
            messageId: 'reply-message-id@example.com',
            inReplyTo: 'sent-message-id@example.com',
            references: ['sent-message-id@example.com'],
            fromEmail: 'ada@example.com',
            subject: 'Re: Hello',
            text: 'Sounds great.',
        ),
    ]);

    $this->app->instance(MailboxReader::class, $reader);

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Replied);

    expect($reader->fetched)->toHaveCount(1)
        ->and($reader->fetched[0]['connection']->is($fixture['connection']))->toBeTrue()
        ->and($reader->fetched[0]['since'])->toBeNull();

    $connection = $fixture['connection']->fresh();

    expect($connection->last_checked_at)->not->toBeNull()
        ->and($connection->last_checked_at->equalTo(CarbonImmutable::now()))->toBeTrue();
});

test('the job reads messages since the previously checked time', function () {
    $since = CarbonImmutable::parse('2026-03-01 09:00:00');

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => $since]);

    $reader = new FakeMailboxReader;
    $this->app->instance(MailboxReader::class, $reader);

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    expect($reader->fetched)->toHaveCount(1)
        ->and($reader->fetched[0]['since']?->equalTo($since))->toBeTrue();
});

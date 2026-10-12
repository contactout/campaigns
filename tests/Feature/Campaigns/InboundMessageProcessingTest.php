<?php

use App\Actions\Mail\ProcessInboundMessage;
use App\Contracts\Mail\CampaignMailer;
use App\Data\InboundMessage;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Tests\Support\RecordingCampaignMailer;

/**
 * Build an active campaign with a connection, contact, recipient and sent email.
 *
 * @return array<string, mixed>
 */
function mmosInboundFixture(): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => null,
        'sent_count' => 0,
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

/**
 * Build an inbound reply referencing the fixture's sent email.
 */
function mmosInboundReply(): InboundMessage
{
    return new InboundMessage(
        messageId: 'reply-message-id@example.com',
        inReplyTo: 'sent-message-id@example.com',
        references: ['sent-message-id@example.com'],
        fromEmail: 'ada@example.com',
        subject: 'Re: Hello',
        text: 'Sounds great, let us talk.',
    );
}

test('an inbound reply marks the email recipient and contact replied', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], mmosInboundReply());

    $email = $fixture['email']->fresh();
    $recipient = $fixture['recipient']->fresh();
    $contact = $fixture['contact']->fresh();

    expect($email->status)->toBe(EmailStatus::Replied)
        ->and($email->replied_at)->not->toBeNull()
        ->and($recipient->status)->toBe(RecipientStatus::Replied)
        ->and($recipient->last_responded_at)->not->toBeNull()
        ->and($contact->status)->toBe(ContactStatus::Replied)
        ->and($contact->last_responded_at)->not->toBeNull();
});

test('a reply matched through the references header is recorded', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'reply-message-id@example.com',
        inReplyTo: null,
        references: ['another-message@example.com', 'sent-message-id@example.com'],
        fromEmail: 'ada@example.com',
        subject: 'Re: Hello',
        text: 'Following up.',
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Replied);
});

test('a replied recipient is not sent further campaign email', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], mmosInboundReply());

    $nextStep = CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 2,
        'day' => 1,
        'time' => '09:00:00',
    ]);

    $nextEmail = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'campaign_step_id' => $nextStep->id,
        'recipient_id' => $fixture['recipient']->id,
        'mailer_connection_id' => $fixture['connection']->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now(),
    ]);

    $mailer = new RecordingCampaignMailer;
    $this->app->instance(CampaignMailer::class, $mailer);

    app()->call([new SendEmail($nextEmail), 'handle']);

    expect($nextEmail->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($mailer->sent)->toBeEmpty();
});

test('a bounce report marks the matching email recipient and contact bounced', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'MAILER-DAEMON@example.com',
        subject: 'Undelivered Mail Returned to Sender',
        text: "This is the mail system at host example.com.\n\n<ada@example.com>: host mx.example.com said: 550 5.1.1 User unknown",
    ));

    $email = $fixture['email']->fresh();
    $recipient = $fixture['recipient']->fresh();
    $contact = $fixture['contact']->fresh();

    expect($email->status)->toBe(EmailStatus::Bounced)
        ->and($recipient->status)->toBe(RecipientStatus::Bounced)
        ->and($recipient->last_delivered_at)->toBeNull()
        ->and($contact->status)->toBe(ContactStatus::Bounced);
});

test('a bounce skips the connections own from address and matches the real recipient', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'postmaster@example.com',
        subject: 'Delivery Status Notification (Failure)',
        text: "From: sender@example.com\n\nYour message to ada@example.com could not be delivered.",
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Bounced);
});

test('a bounce naming an unknown address leaves the campaign untouched', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'postmaster@example.com',
        subject: 'Delivery Status Notification (Failure)',
        text: 'Your message to unknown@example.com could not be delivered.',
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Active)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::NotContacted);
});

test('a non bounce message is ignored', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'newsletter@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'news@example.com',
        subject: 'Weekly digest',
        text: 'Here is what happened this week.',
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Active);
});

test('a bounce report carrying threading headers is recorded as a bounce not a reply', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: 'sent-message-id@example.com',
        references: ['sent-message-id@example.com'],
        fromEmail: 'MAILER-DAEMON@example.com',
        subject: 'Undelivered Mail Returned to Sender',
        text: '<ada@example.com>: 550 5.1.1 User unknown',
    ));

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Bounced)
        ->and($email->replied_at)->toBeNull()
        ->and($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Bounced);
});

test('a bounce matches the original email by the message id quoted in the report', function () {
    $fixture = mmosInboundFixture();

    // The same contact was emailed later by another campaign on this connection.
    $otherCampaign = Campaign::factory()->forTeam($fixture['team'])->active()->create([
        'mailer_connection_id' => $fixture['connection']->id,
    ]);

    $later = CampaignEmail::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'recipient_id' => Recipient::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'contact_id' => $fixture['contact']->id,
        ])->id,
        'mailer_connection_id' => $fixture['connection']->id,
        'message_id' => 'later-message-id@example.com',
        'status' => EmailStatus::Sent,
        'dispatched_at' => CarbonImmutable::now()->addMinute(),
    ]);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'MAILER-DAEMON@example.com',
        subject: 'Undelivered Mail Returned to Sender',
        text: "<ada@example.com>: 550 5.1.1 User unknown\n\nMessage-ID: <sent-message-id@example.com>\nSubject: Hello",
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($later->fresh()->status)->toBe(EmailStatus::Sent);
});

test('a bounce matched by address picks the latest email the connection sent', function () {
    $fixture = mmosInboundFixture();

    $nextStep = CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 2,
        'day' => 1,
        'time' => '09:00:00',
    ]);

    $followUp = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'campaign_step_id' => $nextStep->id,
        'recipient_id' => $fixture['recipient']->id,
        'mailer_connection_id' => $fixture['connection']->id,
        'message_id' => 'follow-up-id@example.com',
        'status' => EmailStatus::Sent,
        'dispatched_at' => CarbonImmutable::now()->addDay(),
    ]);

    $thirdStep = CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 3,
        'day' => 2,
        'time' => '09:00:00',
    ]);

    $unsent = CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'campaign_step_id' => $thirdStep->id,
        'recipient_id' => $fixture['recipient']->id,
        'mailer_connection_id' => $fixture['connection']->id,
        'status' => EmailStatus::Scheduled,
        'dispatched_at' => null,
    ]);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'postmaster@example.com',
        subject: 'Delivery Status Notification (Failure)',
        text: 'Your message to ada@example.com could not be delivered.',
    ));

    expect($followUp->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($unsent->fresh()->status)->toBe(EmailStatus::Scheduled);
});

test('a reply to a step whose subject reads like a bounce is still recorded as a reply', function () {
    $fixture = mmosInboundFixture();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'reply-message-id@example.com',
        inReplyTo: 'sent-message-id@example.com',
        references: ['sent-message-id@example.com'],
        fromEmail: 'ada@example.com',
        subject: 'Re: Undelivered parcels cost you money',
        text: 'Tell me more, ada@example.com is the best address.',
    ));

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Replied)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Replied);
});

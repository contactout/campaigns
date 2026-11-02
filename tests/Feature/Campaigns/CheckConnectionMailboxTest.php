<?php

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Data\MailboxBatch;
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
use Carbon\CarbonInterface;
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
        ->and($reader->fetched[0]['limit'])->toBe(CheckConnectionMailbox::MESSAGES_PER_RUN);

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

test('a connection that was never polled reads from when it was created, not its whole mailbox', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->forceFill(['created_at' => CarbonImmutable::parse('2026-02-01 08:30:00')])->save();

    $reader = new FakeMailboxReader;
    $this->app->instance(MailboxReader::class, $reader);

    app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle']);

    expect($reader->fetched[0]['since']?->toDateTimeString())->toBe('2026-02-01 08:30:00');
});

test('the job advances the cursor to when the fetch started, not when it finished', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();

    $this->app->instance(MailboxReader::class, new class implements MailboxReader
    {
        public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit): MailboxBatch
        {
            // A slow poll: mail arriving now must be read again next time.
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-01 10:07:00'));

            return new MailboxBatch([]);
        }
    });

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    expect($fixture['connection']->fresh()->last_checked_at->toDateTimeString())->toBe('2026-03-01 10:00:00');
});

test('the job keeps the cursor when the mailbox cannot be read', function () {
    $since = CarbonImmutable::parse('2026-03-01 09:00:00');
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => $since]);

    $this->app->instance(MailboxReader::class, new class implements MailboxReader
    {
        public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit): MailboxBatch
        {
            throw new RuntimeException('Graph returned 503.');
        }
    });

    expect(fn () => app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']))
        ->toThrow(RuntimeException::class, 'Graph returned 503.');

    expect($fixture['connection']->fresh()->last_checked_at->equalTo($since))->toBeTrue();
});

/**
 * Build an unmatched inbound message received at the given time.
 */
function mmosMessageAt(string $id, string $receivedAt): InboundMessage
{
    return new InboundMessage(
        messageId: "<{$id}@example.com>",
        inReplyTo: null,
        references: [],
        fromEmail: 'someone@example.com',
        subject: 'Hello',
        text: null,
        receivedAt: CarbonImmutable::parse($receivedAt),
    );
}

test('a truncated batch resumes from the newest message processed and the next run carries on from there', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 06:00:00')]);

    $reader = new FakeMailboxReader([
        mmosMessageAt('a', '2026-03-01 07:00:00'),
        mmosMessageAt('b', '2026-03-01 07:15:30'),
    ], hasMore: true);
    $this->app->instance(MailboxReader::class, $reader);

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    // One second of overlap: Gmail's after: only has second precision.
    expect($fixture['connection']->fresh()->last_checked_at->toDateTimeString())->toBe('2026-03-01 07:15:29');

    $reader->messages = [mmosMessageAt('c', '2026-03-01 08:00:00')];
    $reader->hasMore = false;

    app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle']);

    expect($reader->fetched[1]['since']?->toDateTimeString())->toBe('2026-03-01 07:15:29')
        ->and($fixture['connection']->fresh()->last_checked_at->toDateTimeString())->toBe('2026-03-01 10:00:00');
});

test('a truncated batch packed into one second still moves the cursor forward', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 07:00:00')]);

    $this->app->instance(MailboxReader::class, new FakeMailboxReader([
        mmosMessageAt('a', '2026-03-01 07:00:00'),
        mmosMessageAt('b', '2026-03-01 07:00:00'),
    ], hasMore: true));

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    expect($fixture['connection']->fresh()->last_checked_at->toDateTimeString())->toBe('2026-03-01 07:00:01');
});

test('a truncated batch without received times leaves the cursor for the next run', function () {
    $since = CarbonImmutable::parse('2026-03-01 07:00:00');
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => $since]);

    $this->app->instance(MailboxReader::class, new FakeMailboxReader([], hasMore: true));

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    expect($fixture['connection']->fresh()->last_checked_at->equalTo($since))->toBeTrue();
});

test('reading the same reply again keeps the original reply time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();

    $this->app->instance(MailboxReader::class, new FakeMailboxReader([
        new InboundMessage(
            messageId: 'reply-message-id@example.com',
            inReplyTo: 'sent-message-id@example.com',
            references: ['sent-message-id@example.com'],
            fromEmail: 'ada@example.com',
            subject: 'Re: Hello',
            text: 'Sounds great.',
        ),
    ]));

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:10:00'));

    app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle']);

    expect($fixture['email']->fresh()->replied_at->toDateTimeString())->toBe('2026-03-01 10:00:00')
        ->and($fixture['recipient']->fresh()->last_responded_at->toDateTimeString())->toBe('2026-03-01 10:00:00');
});

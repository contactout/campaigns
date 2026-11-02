<?php

use App\Actions\Mail\ProcessInboundMessage;
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
use App\Services\Mail\SendFailureClassifier;
use App\Services\Mail\SuppressionList;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeMailboxReader;
use Tests\Support\SimulatedMailboxReader;

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
        public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch
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
        public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch
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

test('a truncated batch resumes from the second of the newest message processed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 06:00:00')]);

    $reader = new FakeMailboxReader([
        mmosMessageAt('a', '2026-03-01 07:00:00'),
        mmosMessageAt('b', '2026-03-01 07:15:30.750'),
    ], hasMore: true);
    $this->app->instance(MailboxReader::class, $reader);

    app()->call([new CheckConnectionMailbox($fixture['connection']), 'handle']);

    // Whole seconds: what is stored is exactly what the next run reads from.
    expect($fixture['connection']->fresh()->last_checked_at->format('Y-m-d H:i:s.v'))->toBe('2026-03-01 07:15:30.000')
        ->and($reader->fetched)->toHaveCount(1);
});

/**
 * Build a recording stand-in for the inbound processor.
 */
function mmosRecordingProcessor(): ProcessInboundMessage
{
    $process = new class(app(SuppressionList::class), app(SendFailureClassifier::class)) extends ProcessInboundMessage
    {
        /**
         * @var array<int, string>
         */
        public array $handled = [];

        public function handle(MailerConnection $connection, InboundMessage $message): void
        {
            $this->handled[] = $message->messageId;
        }
    };

    app()->instance(ProcessInboundMessage::class, $process);

    return $process;
}

/**
 * Poll the fixture connection until its cursor reaches the current time.
 *
 * Returns how many messages each poll processed.
 *
 * @param  array<string, mixed>  $fixture
 * @param  object{handled: array<int, string>}  $process
 * @return array<int, int>
 */
function mmosPollUntilCaughtUp(array $fixture, object $process, int $maxPolls = 10): array
{
    $perPoll = [];

    for ($poll = 1; $poll <= $maxPolls; $poll++) {
        $handledBefore = count($process->handled);

        app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle']);

        $perPoll[] = count($process->handled) - $handledBefore;

        if ($fixture['connection']->fresh()->last_checked_at?->equalTo(CarbonImmutable::now())) {
            break;
        }
    }

    return $perPoll;
}

test('more mail in one second than a run takes is all processed across polls', function (string $tieTime) {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 06:00:00')]);

    $mailbox = [];

    foreach (range(1, CheckConnectionMailbox::MESSAGES_PER_RUN + 50) as $i) {
        $mailbox[] = mmosMessageAt("tie-{$i}", $tieTime);
    }

    $mailbox[] = mmosMessageAt('later', '2026-03-01 07:00:05');

    $this->app->instance(MailboxReader::class, new SimulatedMailboxReader($mailbox));
    $process = mmosRecordingProcessor();

    mmosPollUntilCaughtUp($fixture, $process);

    $expected = array_map(fn (InboundMessage $message) => $message->messageId, $mailbox);

    expect(array_values(array_unique($process->handled)))->toEqualCanonicalizing($expected)
        ->and($fixture['connection']->fresh()->last_checked_at->toDateTimeString())->toBe('2026-03-01 10:00:00');
})->with([
    'whole seconds' => '2026-03-01 07:00:00',
    'gmail milliseconds' => '2026-03-01 07:00:00.500',
]);

test('a large backlog spread over time is drained in order across polls', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 06:00:00')]);

    $mailbox = [];

    foreach (range(1, 250) as $i) {
        $mailbox[] = mmosMessageAt("m-{$i}", CarbonImmutable::parse('2026-03-01 07:00:00.250')->addSeconds(intdiv($i, 3))->toDateTimeString('millisecond'));
    }

    $this->app->instance(MailboxReader::class, new SimulatedMailboxReader($mailbox));
    $process = mmosRecordingProcessor();

    $perPoll = mmosPollUntilCaughtUp($fixture, $process);

    expect(array_values(array_unique($process->handled)))->toEqualCanonicalizing(
        array_map(fn (InboundMessage $message) => $message->messageId, $mailbox),
    )->and(count($perPoll))->toBeLessThanOrEqual(5);
});

test('a second holding several runs of mail drains over several runs, a batch at a time, skipping nothing', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 07:00:00')]);

    $mailbox = [];

    foreach (range(1, CheckConnectionMailbox::MESSAGES_PER_RUN * 2 + 50) as $i) {
        $mailbox[] = mmosMessageAt("tie-{$i}", '2026-03-01 07:00:00.500');
    }

    $mailbox[] = mmosMessageAt('later', '2026-03-01 07:00:05');

    $this->app->instance(MailboxReader::class, new SimulatedMailboxReader($mailbox));
    $process = mmosRecordingProcessor();

    $perPoll = mmosPollUntilCaughtUp($fixture, $process);

    // 100 from the first poll, then the checkpointed drain: 100, then the last 50, then the later mail.
    expect($perPoll)->toBe([100, 100, 50, 1])
        ->and($process->handled)->toHaveCount(251)
        ->and(array_values(array_unique($process->handled)))->toEqualCanonicalizing(
            array_map(fn (InboundMessage $message) => $message->messageId, $mailbox),
        )
        ->and(Cache::has('mailbox-boundary:'.$fixture['connection']->id.':'.CarbonImmutable::parse('2026-03-01 07:00:00')->getTimestamp()))->toBeFalse();
});

test('a drain interrupted before its checkpoint is saved redoes only that batch', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

    $fixture = mmosMailboxFixture();
    $fixture['connection']->update(['last_checked_at' => CarbonImmutable::parse('2026-03-01 07:00:00')]);

    $mailbox = [];

    foreach (range(1, CheckConnectionMailbox::MESSAGES_PER_RUN * 2 + 50) as $i) {
        $mailbox[] = mmosMessageAt("tie-{$i}", '2026-03-01 07:00:00');
    }

    $this->app->instance(MailboxReader::class, new SimulatedMailboxReader($mailbox));
    $process = mmosRecordingProcessor();

    // First poll checkpoints the first 100. The second dies part-way, as a worker timeout would.
    app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle']);

    $failing = new class(app(SuppressionList::class), app(SendFailureClassifier::class)) extends ProcessInboundMessage
    {
        public int $calls = 0;

        public function handle(MailerConnection $connection, InboundMessage $message): void
        {
            if (++$this->calls === 40) {
                throw new RuntimeException('Worker timed out.');
            }
        }
    };

    expect(fn () => app()->call([new CheckConnectionMailbox($fixture['connection']->fresh()), 'handle'], ['process' => $failing]))
        ->toThrow(RuntimeException::class, 'Worker timed out.');

    $perPoll = mmosPollUntilCaughtUp($fixture, $process);

    expect($perPoll)->toBe([100, 50, 0])
        ->and(array_values(array_unique($process->handled)))->toEqualCanonicalizing(
            array_map(fn (InboundMessage $message) => $message->messageId, $mailbox),
        );
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

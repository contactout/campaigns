<?php

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\CampaignStatus;
use App\Enums\ContactIdentityType;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactIdentity;
use App\Models\ContactProperty;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Signature;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Test double capturing everything sent through the campaign mailer.
 */
class FakeCampaignMailer implements CampaignMailer
{
    /**
     * @var array<int, array{connection: MailerConnection, to: string, subject: string, html: string, headers: array<string, string>, thread: EmailThread|null}>
     */
    public array $sent = [];

    public ?Throwable $exception = null;

    public SendResult $result;

    public function __construct(?SendResult $result = null)
    {
        $this->result = $result ?? new SendResult;
    }

    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->sent[] = compact('connection', 'to', 'subject', 'html', 'headers', 'thread');

        return $this->result;
    }
}

/**
 * Build an active campaign with a connection, contact, recipient and scheduled email.
 *
 * @return array<string, mixed>
 */
function mmosSendEmailFixture(bool $activeConnection = true): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => $activeConnection ? MailerConnectionStatus::Active : MailerConnectionStatus::Deactivated,
        'sending_limit' => null,
        'sent_count' => 0,
    ]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::now(),
    ]);

    $firstStep = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'subject' => 'Hello {{name}}',
        'body' => '<p>{{email}} / {{company}} / {{unknown}}</p>',
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

    $field = ContactField::factory()->forTeam($team)->create(['name' => 'company']);

    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Analytical Engines',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => RecipientStatus::Active,
    ]);

    $email = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $firstStep->id,
        'recipient_id' => $recipient->id,
        'mailer_connection_id' => $connection->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now(),
    ]);

    return compact('team', 'connection', 'campaign', 'firstStep', 'contact', 'recipient', 'email');
}

/**
 * Run the SendEmail job through the container so dependencies (and the bound
 * mailer fake) are resolved.
 */
function mmosRunSendEmail(CampaignEmail $email): void
{
    app()->call([new SendEmail($email), 'handle']);
}

test('sends the rendered email and schedules the next step', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    $secondStep = CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 2,
        'day' => 1,
        'time' => '09:00:00',
    ]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Sent)
        ->and($email->dispatched_at)->not->toBeNull();

    expect($fixture['connection']->fresh()->sent_count)->toBe(1);

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('ada@example.com')
        ->and($fake->sent[0]['subject'])->toBe('Hello Ada Lovelace')
        ->and($fake->sent[0]['html'])->toStartWith('<p>ada@example.com / Analytical Engines / </p>')
        ->and($fake->sent[0]['html'])->toContain('width="1" height="1"');

    $next = CampaignEmail::query()
        ->where('recipient_id', $fixture['recipient']->id)
        ->where('campaign_step_id', $secondStep->id)
        ->first();

    expect($next)->not->toBeNull()
        ->and($next->status)->toBe(EmailStatus::Scheduled);
});

test('fills the signature tag from the sending connection', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    $signature = Signature::factory()->forTeam($fixture['team'])->create(['body' => '<p>Alex from Sales</p>']);
    $fixture['connection']->update(['signature_id' => $signature->id]);
    $fixture['firstStep']->update([
        'subject' => 'Hi {{signature}}',
        'body' => '<p>Hello</p><p>{{signature}}</p>',
    ]);

    mmosRunSendEmail($fixture['email']);

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['subject'])->toBe('Hi ')
        ->and($fake->sent[0]['html'])->toStartWith('<p>Hello</p><p>Alex from Sales</p>');
});

test('marks the recipient completed after the last step', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    $recipient = $fixture['recipient']->fresh();

    expect($recipient->status)->toBe(RecipientStatus::Completed)
        ->and($recipient->last_delivered_at)->not->toBeNull();

    expect(CampaignEmail::query()->count())->toBe(1);
});

test('does nothing when the campaign is not active', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['status' => CampaignStatus::Stopped]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Scheduled)
        ->and($fake->sent)->toBeEmpty();
});

test('fails the email when there is no active mailer connection', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture(activeConnection: false);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($fake->sent)->toBeEmpty();
});

test('reschedules the email when the connection is rate limited', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-01 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    $fixture['connection']->update([
        'sending_limit' => 1,
        'sent_count' => 1,
        // Inside today's window, so the counter is not reset first.
        'sending_limit_refreshed_at' => CarbonImmutable::now()->addDay(),
    ]);
    $fixture['email']->update(['scheduled_at' => CarbonImmutable::now()]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->equalTo(CarbonImmutable::now()->addMinutes(15)))->toBeTrue();

    $connection = $fixture['connection']->fresh();

    expect($connection->rate_limit_expired_at)->not->toBeNull()
        ->and($connection->rate_limit_expired_at->equalTo($email->scheduled_at))->toBeTrue()
        ->and($fake->sent)->toBeEmpty();
});

test('marks the email failed and deactivates the connection when sending throws', function () {
    $fake = new FakeCampaignMailer;
    $fake->exception = new RuntimeException('SMTP is down');
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed);

    $connection = $fixture['connection']->fresh();

    expect($connection->status)->toBe(MailerConnectionStatus::Deactivated)
        ->and($connection->exception_type)->toBe(RuntimeException::class)
        ->and($connection->exception_data)->toBe(['message' => 'SMTP is down'])
        ->and($connection->threw_at)->not->toBeNull();

    expect($fake->sent)->toBeEmpty();
});

test('persists message_id and thread_id when the mailer returns them', function () {
    $fake = new FakeCampaignMailer(new SendResult(
        messageId: '<msg-123@example.com>',
        threadId: 'thread-abc',
    ));
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Sent)
        ->and($email->message_id)->toBe('<msg-123@example.com>')
        ->and($email->thread_id)->toBe('thread-abc');
});

test('does not send again while the same email is already being sent', function () {
    $fixture = mmosSendEmailFixture();

    $mailer = new class($fixture['email']) implements CampaignMailer
    {
        public int $sends = 0;

        public function __construct(private readonly CampaignEmail $email) {}

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
        {
            $this->sends++;

            // Stand in for a second worker picking up a duplicate job for this
            // email while this send is still in flight. Only once, so a missing
            // lock fails the assertion instead of recursing forever.
            if ($this->sends === 1) {
                app()->call([new SendEmail($this->email), 'handle']);
            }

            return new SendResult;
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    mmosRunSendEmail($fixture['email']);

    expect($mailer->sends)->toBe(1)
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($fixture['connection']->fresh()->sent_count)->toBe(1);
});

test('starts a new sending window when the utc day rolls over', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-01 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    // Yesterday's window, and the limit was already reached in it.
    $fixture['connection']->update([
        'sending_limit' => 1,
        'sent_count' => 1,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-01 00:00:00'),
    ]);

    mmosRunSendEmail($fixture['email']);

    $connection = $fixture['connection']->fresh();

    expect($fake->sent)->toHaveCount(1)
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($connection->sent_count)->toBe(1)
        ->and($connection->sending_limit_refreshed_at->toDateTimeString())->toBe('2026-02-02 00:00:00');
});

test('opens a sending window on the first send for a connection', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-01 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update(['sending_limit' => 5]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['connection']->fresh()->sending_limit_refreshed_at->toDateTimeString())
        ->toBe('2026-02-02 00:00:00');
});

test('does not send an email that has already been sent', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);
    mmosRunSendEmail($fixture['email']);

    expect($fake->sent)->toHaveCount(1)
        ->and($fixture['connection']->fresh()->sent_count)->toBe(1);
});

test('does not send an email that was pushed back to a future time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-01 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    $fixture['email']->update(['scheduled_at' => CarbonImmutable::now()->addMinutes(15)]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Scheduled)
        ->and($fake->sent)->toBeEmpty();
});

test('does not send an email that is still pending', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    $fixture['email']->update(['status' => EmailStatus::Pending]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Pending)
        ->and($fake->sent)->toBeEmpty();
});

test('queues only one job per campaign email', function () {
    config(['queue.default' => 'database']);

    $fixture = mmosSendEmailFixture();
    $other = CampaignEmail::factory()->create();

    SendEmail::dispatch($fixture['email']);
    SendEmail::dispatch($fixture['email']);
    SendEmail::dispatch($fixture['email']->fresh());
    SendEmail::dispatch($other);

    expect(DB::table('jobs')->count())->toBe(2);
});

/**
 * Create a follow-up step for the fixture and return the scheduled email.
 *
 * @param  array<string, mixed>  $fixture
 */
function mmosSendFollowUp(array $fixture, bool $threaded = true): CampaignEmail
{
    $step = CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 2,
        'day' => 1,
        'time' => '09:00:00',
        'is_threaded' => $threaded,
    ]);

    return CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $fixture['recipient']->id,
        'mailer_connection_id' => $fixture['connection']->id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now(),
    ]);
}

test('a threaded follow-up replies to the previous sent step', function () {
    $fake = new FakeCampaignMailer(new SendResult(
        messageId: '<first@example.com>',
        threadId: 'thread-1',
        replyToId: 'provider-reply-1',
    ));
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $followUp = mmosSendFollowUp($fixture, threaded: true);

    mmosRunSendEmail($fixture['email']);

    $fake->result = new SendResult(
        messageId: '<second@example.com>',
        threadId: 'thread-1',
    );

    mmosRunSendEmail($followUp);

    expect($fake->sent)->toHaveCount(2);

    $thread = $fake->sent[1]['thread'];

    expect($thread)->toBeInstanceOf(EmailThread::class)
        ->and($thread->messageId)->toBe('<first@example.com>')
        ->and($thread->threadId)->toBe('thread-1')
        ->and($thread->replyToId)->toBe('provider-reply-1');

    $sentFollowUp = $followUp->fresh();

    expect($sentFollowUp->status)->toBe(EmailStatus::Sent)
        ->and($sentFollowUp->message_id)->toBe('<second@example.com>')
        ->and($sentFollowUp->thread_id)->toBe('thread-1');
});

test('an unthreaded follow-up starts a new conversation', function () {
    $fake = new FakeCampaignMailer(new SendResult(
        messageId: '<first@example.com>',
        threadId: 'thread-1',
    ));
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $followUp = mmosSendFollowUp($fixture, threaded: false);

    mmosRunSendEmail($fixture['email']);
    mmosRunSendEmail($followUp);

    expect($fake->sent)->toHaveCount(2)
        ->and($fake->sent[1]['thread'])->toBeNull();
});

test('a threaded follow-up with no previous message id starts a new conversation', function () {
    $fake = new FakeCampaignMailer(new SendResult);
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $followUp = mmosSendFollowUp($fixture, threaded: true);

    mmosRunSendEmail($fixture['email']);
    mmosRunSendEmail($followUp);

    expect($fake->sent)->toHaveCount(2)
        ->and($fake->sent[1]['thread'])->toBeNull();
});

test('persists the provider reply target returned by the mailer', function () {
    $fake = new FakeCampaignMailer(new SendResult(
        messageId: '<outlook@example.com>',
        threadId: 'conv-1',
        replyToId: 'graph-message-1',
    ));
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->reply_to_id)->toBe('graph-message-1');
});

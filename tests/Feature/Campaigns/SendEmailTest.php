<?php

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\CampaignStatus;
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
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

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

    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): SendResult
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->sent[] = compact('connection', 'to', 'subject', 'html', 'headers', 'thread', 'toName');

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
 * Campaign settings allowing sends 09:00-17:00 on weekdays.
 *
 * @return array<string, mixed>
 */
function mmosWeekdayWindow(): array
{
    return [
        'sending_days' => [1, 2, 3, 4, 5],
        'sending_hour_from' => 9,
        'sending_hour_to' => 17,
    ];
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
        ->and($fake->sent[0]['toName'])->toBe('Ada Lovelace')
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

test('omits the display name when the contact name is blank or an email address', function (string $name) {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);
    $fixture = mmosSendEmailFixture();
    $fixture['contact']->update(['name' => $name]);

    mmosRunSendEmail($fixture['email']);

    expect($fake->sent[0]['to'])->toBe('ada@example.com')
        ->and($fake->sent[0]['toName'])->toBeNull();
})->with(['', '  ', 'ada@example.com']);

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

test('escapes contact values in the body but not in the subject', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['contact']->update(['name' => 'Tom <Ops> & Co']);
    $fixture['firstStep']->update(['body' => '<p>Hi {{name}}</p>']);

    mmosRunSendEmail($fixture['email']);

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['subject'])->toBe('Hello Tom <Ops> & Co')
        ->and($fake->sent[0]['html'])->toStartWith('<p>Hi Tom &lt;Ops&gt; &amp; Co</p>');
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

test('defers the email while its mailer connection is not active', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture(activeConnection: false);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-02 13:00:00')
        ->and($email->data[SendEmail::DEFERRED_FOR_CONNECTION])->toBe($fixture['connection']->id)
        ->and($fake->sent)->toBeEmpty();
});

test('defers the email for an inactive connection inside the sending window', function () {
    // Friday 16:30; an hour later is past the 09:00-17:00 weekday window.
    $this->travelTo(CarbonImmutable::parse('2026-02-06 16:30:00'));

    $this->app->instance(CampaignMailer::class, new FakeCampaignMailer);

    $fixture = mmosSendEmailFixture(activeConnection: false);
    $fixture['campaign']->update(['settings' => mmosWeekdayWindow()]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00');
});

test('fails the email when no mailer connection is set at all', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['mailer_connection_id' => null]);
    $fixture['email']->update(['mailer_connection_id' => null]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($fake->sent)->toBeEmpty();
});

test('records the campaign connection on an email that was seeded without one', function () {
    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['email']->update(['mailer_connection_id' => null]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Sent)
        ->and($email->mailer_connection_id)->toBe($fixture['connection']->id)
        ->and($fake->sent[0]['connection']->is($fixture['connection']))->toBeTrue();
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
        ->and($email->scheduled_at->equalTo(CarbonImmutable::now()->addDay()))->toBeTrue();

    $connection = $fixture['connection']->fresh();

    expect($connection->rate_limit_expired_at)->not->toBeNull()
        ->and($connection->rate_limit_expired_at->equalTo($email->scheduled_at))->toBeTrue()
        ->and($fake->sent)->toBeEmpty();
});

test('reschedules the email within the sending window when the connection is rate limited', function () {
    // Friday 16:50; the cap lifts at midnight, after the 17:00 close.
    $this->travelTo(CarbonImmutable::parse('2026-02-06 16:50:00'));

    $this->app->instance(CampaignMailer::class, new FakeCampaignMailer);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['settings' => mmosWeekdayWindow()]);
    $fixture['connection']->update([
        'sending_limit' => 1,
        'sent_count' => 1,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-07 00:00:00'),
    ]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00')
        ->and($fixture['connection']->fresh()->rate_limit_expired_at->toDateTimeString())->toBe('2026-02-07 00:00:00');
});

test('reschedules an overdue email instead of sending it outside the sending window', function () {
    // Saturday just after the UTC rollover; the email came due on Friday.
    $this->travelTo(CarbonImmutable::parse('2026-02-07 00:05:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['settings' => mmosWeekdayWindow()]);
    $fixture['email']->update(['scheduled_at' => CarbonImmutable::parse('2026-02-06 16:00:00')]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00')
        ->and($fake->sent)->toBeEmpty();
});

test('holds a capped email until the daily sending limit refreshes', function () {
    // Mid-morning: the cap does not lift until the next UTC midnight, so a
    // short retry would only push the same email back again and again.
    $this->travelTo(CarbonImmutable::parse('2026-02-03 10:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update([
        'sending_limit' => 50,
        'sent_count' => 50,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-04 00:00:00'),
    ]);
    $fixture['email']->update(['scheduled_at' => CarbonImmutable::now()]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-04 00:00:00')
        ->and($fixture['connection']->fresh()->rate_limit_expired_at->toDateTimeString())->toBe('2026-02-04 00:00:00')
        ->and($fake->sent)->toBeEmpty();
});

test('defers the email within the sending window while the connection is spacing out sends', function () {
    // Friday 16:59; the interval ends after the 17:00 close.
    $this->travelTo(CarbonImmutable::parse('2026-02-06 16:59:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['settings' => mmosWeekdayWindow()]);
    $fixture['connection']->update([
        'min_seconds_between_sends' => 120,
        'last_sent_at' => CarbonImmutable::now()->subSeconds(30),
    ]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00')
        ->and($fake->sent)->toBeEmpty();
});

test('records the send time once the connection interval has passed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update([
        'min_seconds_between_sends' => 120,
        'last_sent_at' => CarbonImmutable::now()->subSeconds(120),
    ]);

    mmosRunSendEmail($fixture['email']);

    expect($fake->sent)->toHaveCount(1)
        ->and($fixture['connection']->fresh()->last_sent_at->toDateTimeString())->toBe('2026-02-02 12:00:00');
});

test('bounces only the email when the provider rejects the recipient', function () {
    $fake = new FakeCampaignMailer;
    $fake->exception = new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".', 550);
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    CampaignStep::factory()->forCampaign($fixture['campaign'])->create([
        'sequence' => 2,
        'day' => 1,
        'time' => '09:00:00',
    ]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Bounced)
        ->and($email->data['last_error']['type'])->toBe('recipient')
        ->and($email->data['last_error']['message'])->toContain('User unknown')
        ->and($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Bounced)
        ->and($fixture['connection']->fresh()->status)->toBe(MailerConnectionStatus::Active)
        ->and(CampaignEmail::query()->count())->toBe(1);
});

test('retries a transient failure with backoff and keeps the connection active', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $fake->exception = new UnexpectedResponseException('Expected response code "250" but got code "451".', 451);
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-02 12:15:00')
        ->and($email->data['send_attempts'])->toBe(1)
        ->and($email->data['last_error']['type'])->toBe('transient')
        ->and($fixture['connection']->fresh()->status)->toBe(MailerConnectionStatus::Active);

    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:15:00'));

    mmosRunSendEmail($email);

    $email = $email->fresh();

    expect($email->scheduled_at->toDateTimeString())->toBe('2026-02-02 12:45:00')
        ->and($email->data['send_attempts'])->toBe(2);
});

test('retries a transient failure inside the sending window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-06 16:50:00'));

    $fake = new FakeCampaignMailer;
    $fake->exception = new TransportException('Connection to "smtp.example.com:587" timed out.');
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['campaign']->update(['settings' => mmosWeekdayWindow()]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-09 09:00:00');
});

test('fails the email once transient failures use up the attempts', function () {
    $fake = new FakeCampaignMailer;
    $fake->exception = new RuntimeException('Something unexpected');
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['email']->update(['data' => ['send_attempts' => 4]]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Failed)
        ->and($email->data['send_attempts'])->toBe(5)
        ->and($fixture['connection']->fresh()->status)->toBe(MailerConnectionStatus::Active);
});

test('deactivates the connection and defers the email when authentication fails', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $fake->exception = new TransportException('Failed to authenticate on SMTP server with username "mailer-user".', 535);
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-02 13:00:00')
        ->and($email->data[SendEmail::DEFERRED_FOR_CONNECTION])->toBe($fixture['connection']->id)
        ->and($email->data['last_error']['type'])->toBe('connection');

    $connection = $fixture['connection']->fresh();

    expect($connection->status)->toBe(MailerConnectionStatus::Deactivated)
        ->and($connection->exception_type)->toBe(TransportException::class)
        ->and($connection->exception_data['message'])->toContain('Failed to authenticate')
        ->and($connection->threw_at)->not->toBeNull();

    expect($fake->sent)->toBeEmpty();
});

test('clears the deferral flag once the email is sent', function () {
    $this->app->instance(CampaignMailer::class, new FakeCampaignMailer);

    $fixture = mmosSendEmailFixture();
    $fixture['email']->update(['data' => [SendEmail::DEFERRED_FOR_CONNECTION => $fixture['connection']->id]]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Sent)
        ->and($email->data)->not->toHaveKey(SendEmail::DEFERRED_FOR_CONNECTION);
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

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): SendResult
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

/**
 * Add another due email for a new recipient on the fixture's campaign.
 *
 * @param  array<string, mixed>  $fixture
 */
function mmosAnotherEmail(array $fixture, string $address = 'grace@example.com'): CampaignEmail
{
    $contact = Contact::factory()->forTeam($fixture['team'])->create(['name' => 'Grace Hopper']);

    ContactIdentity::factory()->create([
        'team_id' => $fixture['team']->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => $address,
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'contact_id' => $contact->id,
        'status' => RecipientStatus::Active,
    ]);

    return CampaignEmail::factory()->create([
        'campaign_id' => $fixture['campaign']->id,
        'campaign_step_id' => $fixture['firstStep']->id,
        'recipient_id' => $recipient->id,
        'mailer_connection_id' => $fixture['email']->mailer_connection_id,
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now(),
    ]);
}

test('does not let concurrent workers send past the connection sending limit', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update([
        'sending_limit' => 2,
        'sent_count' => 1,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-03 00:00:00'),
    ]);

    $other = mmosAnotherEmail($fixture);

    $mailer = new class($other) implements CampaignMailer
    {
        /**
         * @var array<int, string>
         */
        public array $sent = [];

        public function __construct(private readonly CampaignEmail $other) {}

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): SendResult
        {
            $this->sent[] = $to;

            // Stand in for a second worker sending another email through the
            // same connection while this send is still in flight. Both read
            // the counter below the limit before either send completes.
            if (count($this->sent) === 1) {
                app()->call([new SendEmail($this->other), 'handle']);
            }

            return new SendResult;
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    mmosRunSendEmail($fixture['email']);

    expect($mailer->sent)->toBe(['ada@example.com'])
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($other->fresh()->status)->toBe(EmailStatus::Scheduled)
        ->and($other->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-03 00:00:00')
        ->and($fixture['connection']->fresh()->sent_count)->toBe(2);
});

test('releases the reserved send when the send fails', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $fake->exception = new TransportException('Connection timed out');
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update([
        'sending_limit' => 5,
        'sent_count' => 3,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-03 00:00:00'),
    ]);

    mmosRunSendEmail($fixture['email']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Scheduled)
        ->and($fixture['connection']->fresh()->sent_count)->toBe(3);
});

test('holds the email when another worker takes the last slot after the cap check', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    // One slot left. The email falls back to the campaign's connection so the
    // worker loads it exactly once.
    $fixture['connection']->update([
        'sending_limit' => 2,
        'sent_count' => 1,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-03 00:00:00'),
    ]);
    $fixture['email']->update(['mailer_connection_id' => null]);

    $other = mmosAnotherEmail($fixture);

    // Once the first worker holds a copy showing a free slot, a second
    // worker takes it, so only the reservation can stop the first one.
    $interleaved = false;

    MailerConnection::retrieved(function () use (&$interleaved, $other): void {
        if ($interleaved) {
            return;
        }

        $interleaved = true;
        app()->call([new SendEmail($other), 'handle']);
    });

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect(array_column($fake->sent, 'to'))->toBe(['grace@example.com'])
        ->and($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-02-03 00:00:00')
        ->and($fixture['connection']->fresh()->sent_count)->toBe(2);
});

test('does not give back a slot in the next sending window when a send fails after the rollover', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 23:59:59'));

    $fixture = mmosSendEmailFixture();
    $fixture['connection']->update([
        'sending_limit' => 1,
        'sent_count' => 0,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-03 00:00:00'),
    ]);

    $other = mmosAnotherEmail($fixture);
    $third = mmosAnotherEmail($fixture, 'alan@example.com');

    // While the first send waits on the server, the UTC day rolls over and
    // a second worker opens the new window and sends; then the first fails.
    $mailer = new class(function () use ($other): void {
        $this->travelTo(CarbonImmutable::parse('2026-02-03 00:00:30'));
        app()->call([new SendEmail($other), 'handle']);
    }) implements CampaignMailer
    {
        /**
         * @var array<int, string>
         */
        public array $sent = [];

        public function __construct(private readonly Closure $whileSending) {}

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): SendResult
        {
            $this->sent[] = $to;

            if (count($this->sent) === 1) {
                ($this->whileSending)();

                throw new TransportException('Connection timed out');
            }

            return new SendResult;
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    mmosRunSendEmail($fixture['email']);

    $connection = $fixture['connection']->fresh();

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Scheduled)
        ->and($other->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($connection->sent_count)->toBe(1)
        ->and($connection->sending_limit_refreshed_at->toDateTimeString())->toBe('2026-02-04 00:00:00');

    // The new day's one slot is used, so a third email is held.
    mmosRunSendEmail($third);

    expect($mailer->sent)->toBe(['ada@example.com', 'grace@example.com'])
        ->and($third->fresh()->scheduled_at->toDateTimeString())->toBe('2026-02-04 00:00:00');
});

test('does not reset sends another worker counted in the new sending window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00'));

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    // Yesterday's window, already capped. Both emails fall back to the
    // campaign's connection so each worker loads it exactly once.
    $fixture['connection']->update([
        'sending_limit' => 5,
        'sent_count' => 5,
        'sending_limit_refreshed_at' => CarbonImmutable::parse('2026-02-02 00:00:00'),
    ]);
    $fixture['email']->update(['mailer_connection_id' => null]);

    $other = mmosAnotherEmail($fixture);

    // Once the first worker holds its stale copy of the connection, a second
    // worker opens the new window and sends before the first one continues.
    $interleaved = false;

    MailerConnection::retrieved(function () use (&$interleaved, $other): void {
        if ($interleaved) {
            return;
        }

        $interleaved = true;
        app()->call([new SendEmail($other), 'handle']);
    });

    mmosRunSendEmail($fixture['email']);

    $connection = $fixture['connection']->fresh();

    expect($fake->sent)->toHaveCount(2)
        ->and($connection->sent_count)->toBe(2)
        ->and($connection->sending_limit_refreshed_at->toDateTimeString())->toBe('2026-02-03 00:00:00');
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

test('drops a queued job whose email was deleted before it ran', function () {
    config(['queue.default' => 'database']);

    $fake = new FakeCampaignMailer;
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();

    SendEmail::dispatch($fixture['email']);

    // Removing the recipient cascades to its queued campaign emails.
    $fixture['recipient']->delete();

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($fake->sent)->toBeEmpty();
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

test('records the connection that sent even when a switch repoints the row mid-send', function () {
    $fixture = mmosSendEmailFixture();

    $other = MailerConnection::factory()->forTeam($fixture['team'])->create();

    $mailer = new class($fixture['email'], $other) implements CampaignMailer
    {
        public function __construct(
            private readonly CampaignEmail $email,
            private readonly MailerConnection $other,
        ) {}

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): SendResult
        {
            // Stand in for the campaign switching connection while the
            // provider call is in flight: the still-Scheduled row is moved.
            CampaignEmail::query()->whereKey($this->email->id)->update(['mailer_connection_id' => $this->other->id]);

            return new SendResult(messageId: '<sent@example.com>');
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Sent)
        ->and($email->mailer_connection_id)->toBe($fixture['connection']->id);
});

test('a threaded follow-up on a new connection keeps only the portable message id', function () {
    $fake = new FakeCampaignMailer(new SendResult(
        messageId: '<first@example.com>',
        threadId: 'thread-1',
        replyToId: 'provider-reply-1',
    ));
    $this->app->instance(CampaignMailer::class, $fake);

    $fixture = mmosSendEmailFixture();
    $followUp = mmosSendFollowUp($fixture, threaded: true);

    mmosRunSendEmail($fixture['email']);

    // The campaign moved to another mailbox, which cannot see the first
    // email's provider thread or message.
    $other = MailerConnection::factory()->forTeam($fixture['team'])->create([
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => null,
        'sent_count' => 0,
    ]);
    $followUp->update(['mailer_connection_id' => $other->id]);

    mmosRunSendEmail($followUp);

    expect($fake->sent)->toHaveCount(2)
        ->and($fake->sent[1]['connection']->id)->toBe($other->id);

    $thread = $fake->sent[1]['thread'];

    expect($thread)->toBeInstanceOf(EmailThread::class)
        ->and($thread->messageId)->toBe('<first@example.com>')
        ->and($thread->threadId)->toBeNull()
        ->and($thread->replyToId)->toBeNull();
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

test('queues the email without its loaded relations', function () {
    $fixture = mmosSendEmailFixture();
    $email = $fixture['email']->load(['campaign', 'step', 'recipient.contact', 'mailerConnection']);

    $restored = unserialize(serialize(new SendEmail($email)));

    expect($restored->email->is($email))->toBeTrue()
        ->and(array_keys($restored->email->getRelations()))->toBe([]);
});

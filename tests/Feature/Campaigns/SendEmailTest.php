<?php

use App\Contracts\Mail\CampaignMailer;
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
use App\Models\Team;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Test double capturing everything sent through the campaign mailer.
 */
class FakeCampaignMailer implements CampaignMailer
{
    /**
     * @var array<int, array{connection: MailerConnection, to: string, subject: string, html: string}>
     */
    public array $sent = [];

    public ?Throwable $exception = null;

    public function send(MailerConnection $connection, string $to, string $subject, string $html): void
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->sent[] = compact('connection', 'to', 'subject', 'html');
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

    $fixture['connection']->update(['sending_limit' => 1, 'sent_count' => 1]);
    $fixture['email']->update(['scheduled_at' => CarbonImmutable::now()]);

    mmosRunSendEmail($fixture['email']);

    $email = $fixture['email']->fresh();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->scheduled_at->equalTo(CarbonImmutable::now()->addMinutes(15)))->toBeTrue();

    expect($fixture['connection']->fresh()->rate_limit_expired_at)->not->toBeNull()
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

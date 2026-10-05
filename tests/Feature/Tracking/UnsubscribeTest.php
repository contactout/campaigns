<?php

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
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
use App\Models\Unsubscribe;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RecordingCampaignMailer;

beforeEach(function (): void {
    $this->withoutVite();

    // The React pages are delivered in a later phase; assert component names only.
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Create a campaign recipient with a contact and email identity.
 */
function mmosUnsubscribeRecipient(): Recipient
{
    $team = Team::factory()->create();

    $campaign = Campaign::factory()->forTeam($team)->create();

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Ada Lovelace']);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'ada@example.com',
    ]);

    return Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => RecipientStatus::Active,
    ]);
}

test('an unsigned unsubscribe request is forbidden', function () {
    $recipient = mmosUnsubscribeRecipient();

    $this->get(route('unsubscribe.show', ['recipient' => $recipient]))->assertForbidden();

    $this->post(route('unsubscribe.store', ['recipient' => $recipient]))->assertForbidden();

    expect(Unsubscribe::query()->count())->toBe(0);
});

test('a signed show renders the unsubscribe page', function () {
    $recipient = mmosUnsubscribeRecipient();

    $url = URL::signedRoute('unsubscribe.show', ['recipient' => $recipient]);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('unsubscribe')
            ->where('email', 'ada@example.com')
            ->has('action'));
});

test('a signed store records the unsubscribe and stops the recipient', function () {
    $recipient = mmosUnsubscribeRecipient();

    $url = URL::signedRoute('unsubscribe.store', ['recipient' => $recipient]);

    $this->post($url)->assertRedirect(route('unsubscribe.done'));

    $unsubscribe = Unsubscribe::query()->sole();

    expect($unsubscribe->team_id)->toBe($recipient->campaign->team_id)
        ->and($unsubscribe->campaign_id)->toBe($recipient->campaign_id)
        ->and($unsubscribe->recipient_id)->toBe($recipient->id)
        ->and($unsubscribe->email)->toBe('ada@example.com')
        ->and($unsubscribe->reason)->toBe('recipient');

    expect($recipient->fresh()->status)->toBe(RecipientStatus::Unsubscribed)
        ->and($recipient->contact->fresh()->status)->toBe(ContactStatus::Unsubscribed)
        ->and($recipient->contact->fresh()->do_not_contact_at)->not->toBeNull();
});

/**
 * Build an active campaign ready to send for a single recipient.
 *
 * @return array<string, mixed>
 */
function mmosUnsubscribeSendFixture(): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'sending_limit' => null,
        'sent_count' => 0,
    ]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'UTC',
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'subject' => 'Hello {{name}}',
        'body' => '<p>Hi {{name}}</p>',
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
        'status' => EmailStatus::Scheduled,
        'scheduled_at' => now(),
    ]);

    return compact('team', 'connection', 'campaign', 'step', 'contact', 'recipient', 'email');
}

test('a subsequent send is skipped after the recipient unsubscribes', function () {
    $mailer = new class implements CampaignMailer
    {
        /** @var array<int, array{connection: MailerConnection, to: string, subject: string, html: string, headers: array<string, string>}> */
        public array $sent = [];

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
        {
            $this->sent[] = compact('connection', 'to', 'subject', 'html', 'headers');

            return new SendResult;
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    $fixture = mmosUnsubscribeSendFixture();

    $this->post(URL::signedRoute('unsubscribe.store', ['recipient' => $fixture['recipient']]))
        ->assertRedirect(route('unsubscribe.done'));

    app()->call([new SendEmail($fixture['email']), 'handle']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($mailer->sent)->toBeEmpty();
});

test('an unsubscribed recipient status fails the email without sending', function () {
    $mailer = new class implements CampaignMailer
    {
        /** @var array<int, array{connection: MailerConnection, to: string, subject: string, html: string, headers: array<string, string>}> */
        public array $sent = [];

        public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
        {
            $this->sent[] = compact('connection', 'to', 'subject', 'html', 'headers');

            return new SendResult;
        }
    };

    $this->app->instance(CampaignMailer::class, $mailer);

    $fixture = mmosUnsubscribeSendFixture();

    $fixture['recipient']->update(['status' => RecipientStatus::Unsubscribed]);

    app()->call([new SendEmail($fixture['email']), 'handle']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($mailer->sent)->toBeEmpty();
});

test('a signed one-click post without a csrf token unsubscribes the recipient', function () {
    $recipient = mmosUnsubscribeRecipient();

    $url = URL::signedRoute('unsubscribe.store', ['recipient' => $recipient]);

    // CSRF validation is skipped under the testing environment; enable it.
    $this->app['env'] = 'production';

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect(Unsubscribe::query()->sole()->email)->toBe('ada@example.com')
        ->and($recipient->fresh()->status)->toBe(RecipientStatus::Unsubscribed)
        ->and($recipient->contact->fresh()->status)->toBe(ContactStatus::Unsubscribed);
});

test('an unsigned one-click post is rejected', function () {
    $recipient = mmosUnsubscribeRecipient();

    $this->post(route('unsubscribe.store', ['recipient' => $recipient]), ['List-Unsubscribe' => 'One-Click'])
        ->assertForbidden();

    expect(Unsubscribe::query()->count())->toBe(0)
        ->and($recipient->fresh()->status)->toBe(RecipientStatus::Active);
});

test('a sent email carries signed one-click unsubscribe headers', function () {
    $fixture = mmosUnsubscribeSendFixture();

    $mailer = new RecordingCampaignMailer;
    $this->app->instance(CampaignMailer::class, $mailer);

    app()->call([new SendEmail($fixture['email']), 'handle']);

    expect($mailer->sent)->toHaveCount(1);

    $headers = $mailer->sent[0]['headers'];

    expect($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and($headers['List-Unsubscribe'])->toStartWith('<')->toEndWith('>');

    $url = trim($headers['List-Unsubscribe'], '<>');

    expect($url)->toStartWith(route('unsubscribe.store', ['recipient' => $fixture['recipient']]));

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Unsubscribed);
});

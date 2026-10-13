<?php

use App\Actions\Campaigns\AddCampaignRecipients;
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
use App\Models\ContactList;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\Unsubscribe;
use Carbon\CarbonImmutable;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\Support\RecordingCampaignMailer;

/**
 * Create a team contact with the given email address.
 *
 * @param  array<string, mixed>  $attributes
 */
function suppressionContact(Team $team, string $email, array $attributes = []): Contact
{
    $contact = Contact::factory()->forTeam($team)->create($attributes);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => $email,
    ]);

    return $contact;
}

/**
 * Create an active campaign whose first step is scheduled for the contact.
 *
 * @return array{campaign: Campaign, connection: MailerConnection, recipient: Recipient, email: CampaignEmail}
 */
function suppressionScheduledEmail(Team $team, Contact $contact): array
{
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
        'scheduled_at' => CarbonImmutable::now(),
    ]);

    return compact('campaign', 'connection', 'recipient', 'email');
}

/**
 * Run the send job with a recording mailer bound and return the mailer.
 */
function suppressionSend(CampaignEmail $email): RecordingCampaignMailer
{
    $mailer = new RecordingCampaignMailer;
    app()->instance(CampaignMailer::class, $mailer);

    app()->call([new SendEmail($email), 'handle']);

    return $mailer;
}

/**
 * Build a bounce report about the given address.
 */
function suppressionBounceReport(string $address): InboundMessage
{
    return new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'MAILER-DAEMON@example.com',
        subject: 'Undelivered Mail Returned to Sender',
        text: "<{$address}>: host mx.example.com said: 550 5.1.1 User unknown",
    );
}

test('a hard bounce adds the address to the team suppression list', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);
    $fixture['email']->update(['status' => EmailStatus::Sent, 'dispatched_at' => now()]);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], suppressionBounceReport('ada@example.com'));
    app(ProcessInboundMessage::class)->handle($fixture['connection'], suppressionBounceReport('ada@example.com'));

    $entries = Unsubscribe::query()->where('team_id', $team->id)->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->email)->toBe('ada@example.com')
        ->and($entries->first()->reason)->toBe('bounce')
        ->and($entries->first()->campaign_id)->toBe($fixture['campaign']->id)
        ->and($entries->first()->recipient_id)->toBe($fixture['recipient']->id);
});

test('a delayed delivery report does not add the address to the suppression list', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);
    $fixture['email']->update(['status' => EmailStatus::Sent, 'dispatched_at' => now()]);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'delay-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'mailer-daemon@googlemail.com',
        subject: 'Delivery Status Notification (Delay)',
        text: "There was a temporary problem delivering your message to ada@example.com. Gmail will retry for 47 more hours.\n\n451 4.4.1 Temporary failure, retry later",
    ));

    expect(Unsubscribe::query()->where('team_id', $team->id)->exists())->toBeFalse()
        ->and($contact->fresh()->status)->toBe(ContactStatus::Bounced);
});

test('a permanent failure identified by its enhanced status code is added to the suppression list', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);
    $fixture['email']->update(['status' => EmailStatus::Sent, 'dispatched_at' => now()]);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], new InboundMessage(
        messageId: 'bounce-message-id@example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'postmaster@example.com',
        subject: 'Delivery Status Notification (Failure)',
        text: "Final-Recipient: rfc822; ada@example.com\nAction: failed\nStatus: 5.1.1",
    ));

    expect(Unsubscribe::query()->where('team_id', $team->id)->value('email'))->toBe('ada@example.com');
});

test('a recipient rejected at send time is added to the team suppression list', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);

    $mailer = new RecordingCampaignMailer;
    $mailer->exception = new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".', 550);
    app()->instance(CampaignMailer::class, $mailer);

    app()->call([new SendEmail($fixture['email']), 'handle']);

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and(Unsubscribe::query()->where('team_id', $team->id)->where('email', 'ada@example.com')->value('reason'))->toBe('bounce');
});

test('a hard bounce keeps an existing unsubscribe entry for the address', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);
    $fixture['email']->update(['status' => EmailStatus::Sent, 'dispatched_at' => now()]);

    Unsubscribe::factory()->forTeam($team)->create(['email' => 'ada@example.com', 'reason' => 'recipient']);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], suppressionBounceReport('ada@example.com'));

    expect(Unsubscribe::query()->where('team_id', $team->id)->pluck('reason')->all())->toBe(['recipient'])
        ->and($contact->fresh()->status)->toBe(ContactStatus::Bounced);
});

test('a contact flagged do not contact is not emailed', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com', ['do_not_contact_at' => now()]);
    $fixture = suppressionScheduledEmail($team, $contact);

    $mailer = suppressionSend($fixture['email']);

    expect($mailer->sent)->toBeEmpty()
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed);
});

test('a contact with a suppressed status is not emailed', function (ContactStatus $status) {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com', ['status' => $status]);
    $fixture = suppressionScheduledEmail($team, $contact);

    $mailer = suppressionSend($fixture['email']);

    expect($mailer->sent)->toBeEmpty()
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed);
})->with([
    'bounced' => ContactStatus::Bounced,
    'unsubscribed' => ContactStatus::Unsubscribed,
    'do not contact' => ContactStatus::DoNotContact,
]);

test('a domain entry suppresses every address at that domain regardless of case', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);

    Unsubscribe::factory()->forTeam($team)->create(['email' => '@Example.COM', 'reason' => 'manual']);

    $mailer = suppressionSend($fixture['email']);

    expect($mailer->sent)->toBeEmpty()
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Failed);
});

test('a domain entry does not suppress other domains or other teams', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');
    $fixture = suppressionScheduledEmail($team, $contact);

    Unsubscribe::factory()->forTeam($team)->create(['email' => '@mail.example.com']);
    Unsubscribe::factory()->forTeam(Team::factory()->create())->create(['email' => '@example.com']);

    $mailer = suppressionSend($fixture['email']);

    expect($mailer->sent)->toHaveCount(1)
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Sent);
});

test('a contact that hard bounced in one campaign is not emailed from another', function () {
    $team = Team::factory()->create();
    $contact = suppressionContact($team, 'ada@example.com');

    $first = suppressionScheduledEmail($team, $contact);
    $first['email']->update(['status' => EmailStatus::Sent, 'dispatched_at' => now()]);

    app(ProcessInboundMessage::class)->handle($first['connection'], suppressionBounceReport('ada@example.com'));

    $second = suppressionScheduledEmail($team, $contact);

    $mailer = suppressionSend($second['email']);

    expect($mailer->sent)->toBeEmpty()
        ->and($second['email']->fresh()->status)->toBe(EmailStatus::Failed);

    // The address stays suppressed even once the contact's status is cleared.
    $contact->update(['status' => ContactStatus::Contacted]);
    $second['email']->update(['status' => EmailStatus::Scheduled]);

    $mailer = suppressionSend($second['email']);

    expect($mailer->sent)->toBeEmpty();
});

test('suppressed contacts are skipped at enrollment', function () {
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->forTeam($team)->create();

    $allowed = suppressionContact($team, 'grace@allowed.test');
    $listedAddress = suppressionContact($team, 'listed@allowed.test');
    $listedDomain = suppressionContact($team, 'ada@BLOCKED.test');
    $doNotContact = suppressionContact($team, 'dnc@allowed.test', ['do_not_contact_at' => now()]);
    $bounced = suppressionContact($team, 'bounced@allowed.test', ['status' => ContactStatus::Bounced]);
    $unsubscribed = suppressionContact($team, 'unsub@allowed.test', ['status' => ContactStatus::Unsubscribed]);
    $withoutEmail = Contact::factory()->forTeam($team)->create();

    Unsubscribe::factory()->forTeam($team)->create(['email' => 'LISTED@allowed.test']);
    Unsubscribe::factory()->forTeam($team)->create(['email' => '@blocked.test']);

    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $list->contacts()->attach([$listedAddress->id, $bounced->id]);

    $created = app(AddCampaignRecipients::class)->handle(
        $campaign,
        [$allowed->id, $listedDomain->id, $doNotContact->id, $unsubscribed->id, $withoutEmail->id],
        [$list->id],
    );

    expect($created)->toBe(2)
        ->and($campaign->recipients()->pluck('contact_id')->sort()->values()->all())
        ->toBe(collect([$allowed->id, $withoutEmail->id])->sort()->values()->all());
});

test('enrollment adds nothing when every contact is suppressed', function () {
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->forTeam($team)->create();
    $contact = suppressionContact($team, 'ada@example.com', ['status' => ContactStatus::Bounced]);

    expect(app(AddCampaignRecipients::class)->handle($campaign, [$contact->id], []))->toBe(0)
        ->and($campaign->recipients()->count())->toBe(0);
});

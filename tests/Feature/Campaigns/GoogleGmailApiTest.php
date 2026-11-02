<?php

use App\Actions\Mail\ProcessInboundMessage;
use App\Data\InboundMessage;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\Unsubscribe;
use App\Services\Mail\GoogleGmailApi;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Encode text the way the Gmail API returns body data.
 */
function gmailApiData(string $text): string
{
    return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
}

/**
 * Build a Gmail API message part.
 *
 * @param  array<int, array<string, mixed>>  $parts
 * @param  array<string, string>  $headers
 * @return array<string, mixed>
 */
function gmailApiPart(string $mimeType, ?string $text = null, array $parts = [], array $headers = [], string $filename = ''): array
{
    return [
        'mimeType' => $mimeType,
        'filename' => $filename,
        'headers' => array_map(
            fn (string $name, string $value): array => ['name' => $name, 'value' => $value],
            array_keys($headers),
            array_values($headers),
        ),
        'body' => $text === null ? ['size' => 0] : ['size' => strlen($text), 'data' => gmailApiData($text)],
        'parts' => $parts,
    ];
}

/**
 * Build a Gmail API part whose body is stored as an attachment.
 *
 * @return array<string, mixed>
 */
function gmailApiStoredPart(string $mimeType, string $attachmentId, string $filename = ''): array
{
    return [
        'mimeType' => $mimeType,
        'filename' => $filename,
        'headers' => [],
        'body' => ['attachmentId' => $attachmentId, 'size' => 120],
    ];
}

/**
 * Fetch a message through the adapter with the Gmail API answering the given payload.
 *
 * Attachment bodies are answered in order after the message, and the paths
 * of all requests made are collected in `$paths`.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<int, string>  $attachments
 * @param  array<int, string>|null  $paths
 * @return array<string, mixed>|null
 */
function gmailApiFetch(array $payload, array $attachments = [], ?array &$paths = null): ?array
{
    $json = fn (array $body): Response => new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));

    $responses = [$json([
        'id' => 'gmail-id',
        'threadId' => 'gmail-thread',
        'snippet' => 'Address not found Your message wasn&#39;t delivered to ada@example.com',
        'internalDate' => '1700000000000',
        'payload' => $payload,
    ])];

    foreach ($attachments as $text) {
        $responses[] = $json(['size' => strlen($text), 'data' => gmailApiData($text)]);
    }

    $history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    try {
        return (new GoogleGmailApi(new Client(['handler' => $stack])))->getMessage('access-token', 'gmail-id');
    } finally {
        $paths = array_map(fn (array $transaction): string => $transaction['request']->getUri()->getPath(), $history);
    }
}

/**
 * Build a Gmail bounce report about the fixture's sent email.
 *
 * @return array<string, mixed>
 */
function gmailApiBounce(string $status, string $quotedBody = 'Hi Ada'): array
{
    return gmailApiPart('multipart/report', parts: [
        gmailApiPart('multipart/alternative', parts: [
            gmailApiPart('text/plain', "** Address not found **\r\n\r\nYour message wasn't delivered to ada@example.com.\r\n"),
            gmailApiPart('text/html', '<html><body>Address not found</body></html>'),
        ]),
        gmailApiPart('message/delivery-status', "Reporting-MTA: dns; googlemail.com\r\n\r\nFinal-Recipient: rfc822; ada@example.com\r\nAction: failed\r\nStatus: {$status}\r\n"),
        gmailApiPart('message/rfc822', parts: [
            gmailApiPart('text/plain', $quotedBody, headers: [
                'From' => 'Sender <sender@example.com>',
                'To' => 'ada@example.com',
                'Subject' => 'Hello',
                'Message-ID' => '<sent-message-id@example.com>',
            ]),
        ]),
    ], headers: [
        'From' => 'Mail Delivery Subsystem <mailer-daemon@googlemail.com>',
        'Subject' => 'Delivery Status Notification (Failure)',
        'Message-ID' => '<bounce-id@mx.google.com>',
    ]);
}

/**
 * Turn a fetched Gmail message into the inbound shape the reader builds.
 *
 * @param  array<string, mixed>  $message
 */
function gmailApiInbound(array $message): InboundMessage
{
    return new InboundMessage(
        messageId: $message['messageId'],
        inReplyTo: $message['inReplyTo'],
        references: $message['references'],
        fromEmail: $message['fromEmail'],
        subject: $message['subject'],
        text: $message['text'],
    );
}

/**
 * Build a connection and the email it sent to ada@example.com.
 *
 * @return array{team: Team, connection: MailerConnection, contact: Contact, recipient: Recipient, email: CampaignEmail}
 */
function gmailApiSentEmail(): array
{
    $team = Team::factory()->create();

    $connection = MailerConnection::factory()->forTeam($team)->create([
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => ['from_email' => 'sender@example.com', 'email' => 'sender@example.com'],
    ]);

    $campaign = Campaign::factory()->forTeam($team)->active()->create([
        'mailer_connection_id' => $connection->id,
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::now(),
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $contact = Contact::factory()->forTeam($team)->create();

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
        'message_id' => '<sent-message-id@example.com>',
        'status' => EmailStatus::Sent,
        'dispatched_at' => CarbonImmutable::now(),
    ]);

    return compact('team', 'connection', 'contact', 'recipient', 'email');
}

test('a multipart bounce is read from its parts instead of the snippet', function () {
    $text = gmailApiFetch(gmailApiBounce('5.1.1'))['text'];

    expect($text)->toContain("Your message wasn't delivered to ada@example.com.")
        ->toContain('Status: 5.1.1')
        ->toContain("Content-Type: message/rfc822\nFrom: Sender <sender@example.com>")
        ->toContain('Message-ID: <sent-message-id@example.com>')
        ->not->toContain('<html>')
        ->and(strpos($text, 'Status: 5.1.1'))->toBeLessThan(strpos($text, 'Content-Type: message/rfc822'));
});

test('a multipart Gmail bounce suppresses the address', function () {
    $fixture = gmailApiSentEmail();

    app(ProcessInboundMessage::class)->handle(
        $fixture['connection'],
        gmailApiInbound(gmailApiFetch(gmailApiBounce('5.1.1'))),
    );

    expect($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Bounced)
        ->and(Unsubscribe::query()->where('team_id', $fixture['team']->id)->value('email'))->toBe('ada@example.com');
});

test('a failure code in the quoted original does not condemn a delayed Gmail bounce', function () {
    $fixture = gmailApiSentEmail();

    app(ProcessInboundMessage::class)->handle(
        $fixture['connection'],
        gmailApiInbound(gmailApiFetch(gmailApiBounce('4.4.1', "Hi Ada\r\n550 5.1.1 is our office suite number"))),
    );

    expect($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->not->toBe(ContactStatus::Bounced)
        ->and(Unsubscribe::query()->where('team_id', $fixture['team']->id)->exists())->toBeFalse();
});

test('a bounce returning only the original headers is matched by their Message-ID', function () {
    $fixture = gmailApiSentEmail();

    $message = gmailApiFetch(gmailApiPart('multipart/report', parts: [
        gmailApiPart('text/plain', "Delivery to the following recipient failed permanently:\r\n\r\n    someone-else@example.org\r\n"),
        gmailApiPart('message/delivery-status', "Final-Recipient: rfc822; someone-else@example.org\r\nAction: failed\r\nStatus: 5.1.1\r\n"),
        gmailApiPart('text/rfc822-headers', "From: sender@example.com\r\nMessage-ID: <sent-message-id@example.com>\r\nSubject: Hello\r\n"),
    ], headers: [
        'From' => 'MAILER-DAEMON@example.org',
        'Subject' => 'Undelivered Mail Returned to Sender',
    ]));

    app(ProcessInboundMessage::class)->handle($fixture['connection'], gmailApiInbound($message));

    expect($message['text'])->toContain("Content-Type: text/rfc822-headers\nFrom: sender@example.com")
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Bounced);
});

test('a failure code in returned original headers does not condemn a policy refusal', function () {
    $fixture = gmailApiSentEmail();

    app(ProcessInboundMessage::class)->handle($fixture['connection'], gmailApiInbound(gmailApiFetch(gmailApiPart('multipart/report', parts: [
        gmailApiPart('text/plain', "Your message to ada@example.com was rejected by the recipient's server.\r\n"),
        gmailApiPart('message/delivery-status', "Final-Recipient: rfc822; ada@example.com\r\nAction: failed\r\nStatus: 5.7.1\r\n"),
        gmailApiPart('text/rfc822-headers', "From: sender@example.com\r\nMessage-ID: <sent-message-id@example.com>\r\nSubject: Agenda item 550 5.1.1 review\r\n"),
    ], headers: [
        'From' => 'MAILER-DAEMON@example.org',
        'Subject' => 'Undelivered Mail Returned to Sender',
    ]))));

    expect($fixture['recipient']->fresh()->status)->toBe(RecipientStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->not->toBe(ContactStatus::Bounced)
        ->and(Unsubscribe::query()->where('team_id', $fixture['team']->id)->exists())->toBeFalse();
});

test('attachments and parts without a body are left out of the message text', function () {
    $message = gmailApiFetch(gmailApiPart('multipart/mixed', parts: [
        gmailApiPart('text/plain', 'Sounds great, let us talk.'),
        ['mimeType' => 'text/plain'],
        gmailApiPart('text/csv', "email\r\nbob@example.com", filename: 'contacts.csv'),
    ], headers: ['From' => 'ada@example.com', 'Subject' => 'Re: Hello']));

    expect($message['text'])->toBe('Sounds great, let us talk.');
});

test('report parts Gmail stored as attachments are fetched in order', function () {
    $fixture = gmailApiSentEmail();

    $message = gmailApiFetch(gmailApiPart('multipart/report', parts: [
        gmailApiPart('text/plain', "Delivery to the following recipient failed permanently.\r\n"),
        gmailApiStoredPart('message/delivery-status', 'dsn-attachment'),
        gmailApiStoredPart('text/rfc822-headers', 'headers-attachment'),
    ], headers: [
        'From' => 'Mail Delivery Subsystem <mailer-daemon@googlemail.com>',
        'Subject' => 'Delivery Status Notification (Failure)',
    ]), [
        "Final-Recipient: rfc822; ada@example.com\r\nAction: failed\r\nStatus: 5.1.1\r\n",
        "From: sender@example.com\r\nMessage-ID: <sent-message-id@example.com>\r\n",
    ], $paths);

    app(ProcessInboundMessage::class)->handle($fixture['connection'], gmailApiInbound($message));

    expect($paths)->toBe([
        '/gmail/v1/users/me/messages/gmail-id',
        '/gmail/v1/users/me/messages/gmail-id/attachments/dsn-attachment',
        '/gmail/v1/users/me/messages/gmail-id/attachments/headers-attachment',
    ])
        ->and($message['text'])->toContain("failed permanently.\r\n\nFinal-Recipient: rfc822; ada@example.com")
        ->toContain("Status: 5.1.1\r\n\nContent-Type: text/rfc822-headers\nFrom: sender@example.com")
        ->and($fixture['email']->fresh()->status)->toBe(EmailStatus::Bounced)
        ->and($fixture['contact']->fresh()->status)->toBe(ContactStatus::Bounced)
        ->and(Unsubscribe::query()->where('team_id', $fixture['team']->id)->value('email'))->toBe('ada@example.com');
});

test('a file attached with a filename is never fetched', function () {
    $message = gmailApiFetch(gmailApiPart('multipart/mixed', parts: [
        gmailApiPart('text/plain', 'Sounds great, let us talk.'),
        gmailApiStoredPart('text/plain', 'notes-attachment', filename: 'notes.txt'),
        gmailApiStoredPart('text/rfc822-headers', 'headers-attachment', filename: 'headers.txt'),
    ], headers: ['From' => 'ada@example.com', 'Subject' => 'Re: Hello']), paths: $paths);

    expect($paths)->toBe(['/gmail/v1/users/me/messages/gmail-id'])
        ->and($message['text'])->toBe("Sounds great, let us talk.\nContent-Type: text/rfc822-headers");
});

test('a single part message is still read from its top-level body', function () {
    $message = gmailApiFetch(gmailApiPart('text/plain', 'Sounds great, let us talk.', headers: [
        'From' => 'Ada <ada@example.com>',
        'Subject' => 'Re: Hello',
    ]));

    expect($message['text'])->toBe('Sounds great, let us talk.')
        ->and($message['fromEmail'])->toBe('ada@example.com');
});

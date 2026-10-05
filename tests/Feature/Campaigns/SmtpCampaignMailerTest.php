<?php

use App\Data\EmailThread;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Services\Mail\SmtpCampaignMailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * Transport double that records the MIME messages handed to it.
 */
class FakeSmtpTransport extends AbstractTransport
{
    /**
     * @var array<int, Email>
     */
    public array $messages = [];

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if ($original instanceof Email) {
            $this->messages[] = $original;
        }
    }

    public function __toString(): string
    {
        return 'fake://';
    }
}

/**
 * Build the SMTP mailer with the recording transport.
 */
function mmosSmtpMailer(FakeSmtpTransport $transport): SmtpCampaignMailer
{
    return new class($transport) extends SmtpCampaignMailer
    {
        public function __construct(private readonly FakeSmtpTransport $fakeTransport) {}

        /**
         * @param  array<string, mixed>  $settings
         */
        protected function transport(array $settings): TransportInterface
        {
            return $this->fakeTransport;
        }
    };
}

function mmosSmtpConnection(): MailerConnection
{
    return MailerConnection::factory()->forTeam(Team::factory()->create())->create([
        'mailer_type' => MailerType::Smtp,
        'status' => MailerConnectionStatus::Active,
        'smtp_setting' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'ada@example.com',
            'from_name' => 'Ada',
        ],
    ]);
}

test('smtp mailer replies to the previous message when threaded', function () {
    $transport = new FakeSmtpTransport;

    $result = mmosSmtpMailer($transport)->send(
        mmosSmtpConnection(),
        'to@example.com',
        'Hello',
        '<p>Hi</p>',
        [],
        new EmailThread(messageId: 'first@example.com'),
    );

    expect($transport->messages)->toHaveCount(1);

    $message = $transport->messages[0];

    expect($message->getHeaders()->get('In-Reply-To')?->getBodyAsString())->toBe('<first@example.com>')
        ->and($message->getHeaders()->get('References')?->getBodyAsString())->toBe('<first@example.com>');

    expect($result->messageId)->not->toBeNull()
        ->and($result->messageId)->toStartWith('<')
        ->and($result->messageId)->toEndWith('>')
        ->and($result->messageId)->toContain('@');
});

test('smtp mailer starts a new conversation without a thread context', function () {
    $transport = new FakeSmtpTransport;

    mmosSmtpMailer($transport)->send(
        mmosSmtpConnection(),
        'to@example.com',
        'Hello',
        '<p>Hi</p>',
    );

    $message = $transport->messages[0];

    expect($message->getHeaders()->get('In-Reply-To'))->toBeNull()
        ->and($message->getHeaders()->get('References'))->toBeNull();
});

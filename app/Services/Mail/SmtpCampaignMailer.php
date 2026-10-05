<?php

namespace App\Services\Mail;

use App\Concerns\AppliesThreadHeaders;
use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Models\MailerConnection;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends campaign emails over SMTP using Symfony Mailer.
 *
 * The connection's encrypted SMTP settings are translated into a Symfony
 * Mailer DSN at send time. Credentials are never logged. SMTP has no provider
 * thread, so follow-ups thread by setting the `In-Reply-To`/`References`
 * headers from the previous message id.
 */
class SmtpCampaignMailer implements CampaignMailer
{
    use AppliesThreadHeaders;

    /**
     * Send an HTML email using the given connection's SMTP credentials.
     *
     * @param  array<string, string>  $headers
     *
     * @throws TransportExceptionInterface When the message cannot be sent.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
    {
        $settings = $connection->smtp_setting ?? [];

        $email = (new Email)
            ->from(new Address(
                (string) ($settings['from_email'] ?? ''),
                (string) ($settings['from_name'] ?? ''),
            ))
            ->to($to)
            ->subject($subject)
            ->html($html);

        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        $this->applyThreadHeaders($email, $thread);

        $sent = $this->transport($settings)->send($email);

        // Symfony reports the Message-ID without the angle brackets that the
        // header itself uses, so restore them to match the format providers and
        // inbound readers give us.
        $messageId = $sent?->getMessageId();

        return new SendResult(
            messageId: $messageId !== null && $messageId !== '' ? '<'.trim($messageId, '<>').'>' : null,
        );
    }

    /**
     * Build the transport used to deliver the message.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function transport(array $settings): TransportInterface
    {
        return Transport::fromDsn($this->dsn($settings));
    }

    /**
     * Build a Symfony Mailer DSN from the connection settings.
     *
     * @param  array<string, mixed>  $settings
     */
    private function dsn(array $settings): string
    {
        $scheme = ($settings['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp';
        $host = (string) ($settings['host'] ?? '');
        $port = $settings['port'] ?? null;
        $username = (string) ($settings['username'] ?? '');
        $password = (string) ($settings['password'] ?? '');

        $credentials = '';

        if ($username !== '') {
            $credentials = rawurlencode($username);

            if ($password !== '') {
                $credentials .= ':'.rawurlencode($password);
            }

            $credentials .= '@';
        }

        $address = $host;

        if ($port !== null && $port !== '') {
            $address .= ':'.(string) $port;
        }

        return sprintf('%s://%s%s', $scheme, $credentials, $address);
    }
}

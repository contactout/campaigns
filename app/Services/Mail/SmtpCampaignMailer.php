<?php

namespace App\Services\Mail;

use App\Contracts\Mail\CampaignMailer;
use App\Data\SendResult;
use App\Models\MailerConnection;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends campaign emails over SMTP using Symfony Mailer.
 *
 * The connection's encrypted SMTP settings are translated into a Symfony
 * Mailer DSN at send time. Credentials are never logged.
 */
class SmtpCampaignMailer implements CampaignMailer
{
    /**
     * Send an HTML email using the given connection's SMTP credentials.
     *
     * @throws TransportExceptionInterface When the message cannot be sent.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html): SendResult
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

        Transport::fromDsn($this->dsn($settings))->send($email);

        return new SendResult;
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

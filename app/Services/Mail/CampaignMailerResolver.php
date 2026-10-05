<?php

namespace App\Services\Mail;

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\MailerType;
use App\Models\MailerConnection;

/**
 * Routes campaign sends to the mailer implementation for the connection type.
 */
class CampaignMailerResolver implements CampaignMailer
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        private readonly SmtpCampaignMailer $smtp,
        private readonly GmailCampaignMailer $gmail,
        private readonly OutlookCampaignMailer $outlook,
    ) {}

    /**
     * Send through the mailer matching the connection's type.
     *
     * @param  array<string, string>  $headers
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
    {
        return match ($connection->mailer_type) {
            MailerType::Smtp => $this->smtp->send($connection, $to, $subject, $html, $headers, $thread),
            MailerType::Gmail => $this->gmail->send($connection, $to, $subject, $html, $headers, $thread),
            MailerType::Outlook => $this->outlook->send($connection, $to, $subject, $html, $headers, $thread),
        };
    }
}

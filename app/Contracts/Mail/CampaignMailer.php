<?php

namespace App\Contracts\Mail;

use App\Data\SendResult;
use App\Models\MailerConnection;

/**
 * Sends a single rendered campaign email through a mailer connection.
 *
 * Implementations are responsible for talking to the provider; callers only
 * supply the recipient and the already-rendered subject/body.
 */
interface CampaignMailer
{
    /**
     * Send an HTML email to the given address using the connection's credentials.
     *
     * @param  array<string, string>  $headers  Extra message headers such as List-Unsubscribe.
     *
     * @throws \Throwable When the provider rejects or cannot send the message.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = []): SendResult;
}

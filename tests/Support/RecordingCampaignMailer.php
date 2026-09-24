<?php

namespace Tests\Support;

use App\Contracts\Mail\CampaignMailer;
use App\Data\SendResult;
use App\Models\MailerConnection;

/**
 * Test double capturing everything sent through the campaign mailer.
 */
class RecordingCampaignMailer implements CampaignMailer
{
    /**
     * @var array<int, array{connection: MailerConnection, to: string, subject: string, html: string}>
     */
    public array $sent = [];

    public ?\Throwable $exception = null;

    public SendResult $result;

    public function __construct(?SendResult $result = null)
    {
        $this->result = $result ?? new SendResult;
    }

    public function send(MailerConnection $connection, string $to, string $subject, string $html): SendResult
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->sent[] = compact('connection', 'to', 'subject', 'html');

        return $this->result;
    }
}

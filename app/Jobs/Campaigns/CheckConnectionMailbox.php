<?php

namespace App\Jobs\Campaigns;

use App\Actions\Mail\ProcessInboundMessage;
use App\Contracts\Mail\MailboxReader;
use App\Models\MailerConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polls a single mailer connection's mailbox for replies and bounces.
 */
class CheckConnectionMailbox implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public MailerConnection $mailerConnection) {}

    /**
     * Fetch new messages, process them, and record when the mailbox was read.
     */
    public function handle(MailboxReader $reader, ProcessInboundMessage $process): void
    {
        $connection = $this->mailerConnection;

        $messages = $reader->fetch($connection, $connection->last_checked_at);

        foreach ($messages as $message) {
            $process->handle($connection, $message);
        }

        $connection->last_checked_at = now();
        $connection->save();
    }
}

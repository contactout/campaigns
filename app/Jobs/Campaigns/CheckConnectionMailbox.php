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
     *
     * The cursor only moves once every fetched message has been processed, and
     * it moves to when the fetch started rather than when it finished, so mail
     * that arrives during a slow poll is read again next time. A failed fetch
     * throws and leaves the cursor where it was, so the next poll retries the
     * same window. Processing is idempotent, so re-reading overlap is safe.
     */
    public function handle(MailboxReader $reader, ProcessInboundMessage $process): void
    {
        $connection = $this->mailerConnection;
        $startedAt = now();

        $messages = $reader->fetch($connection, $connection->last_checked_at);

        foreach ($messages as $message) {
            $process->handle($connection, $message);
        }

        $connection->last_checked_at = $startedAt;
        $connection->save();
    }
}

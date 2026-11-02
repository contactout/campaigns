<?php

namespace App\Jobs\Campaigns;

use App\Actions\Mail\ProcessInboundMessage;
use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Models\MailerConnection;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polls a single mailer connection's mailbox for replies and bounces.
 */
class CheckConnectionMailbox implements ShouldQueue
{
    use Queueable;

    /**
     * The most messages read and processed in one run, so a backlog drains
     * over several polls instead of outlasting the worker timeout.
     */
    public const int MESSAGES_PER_RUN = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(public MailerConnection $mailerConnection) {}

    /**
     * Fetch new messages, process them, and record how far the mailbox was read.
     *
     * A connection that was never polled reads from when it was created, not
     * its whole history: nothing older can answer mail sent through it.
     *
     * The cursor only moves once every fetched message has been processed. A
     * complete batch moves it to when the fetch started, not when it finished,
     * so mail that arrives during a slow poll is read again next time. A
     * truncated batch moves it to just before the newest message processed,
     * so the next run carries on from there. A failed fetch throws and leaves
     * the cursor where it was. Processing is idempotent, so overlap is safe.
     */
    public function handle(MailboxReader $reader, ProcessInboundMessage $process): void
    {
        $connection = $this->mailerConnection;
        $startedAt = now();
        $since = $connection->last_checked_at ?? $connection->created_at;

        $batch = $reader->fetch($connection, $since, self::MESSAGES_PER_RUN);

        foreach ($batch->messages as $message) {
            $process->handle($connection, $message);
        }

        $cursor = $batch->hasMore ? $this->resumePoint($batch->messages, $since) : $startedAt;

        if ($cursor === null) {
            return;
        }

        $connection->last_checked_at = $cursor;
        $connection->save();
    }

    /**
     * Find where to resume after a truncated batch.
     *
     * Steps back a second from the newest message read, because Gmail's
     * `after:` only has second precision. It always moves at least a second
     * past `$since`, so a batch that all arrived in the same second cannot
     * stall the mailbox. Returns null when no message carried a received time,
     * so the cursor stays put and the next run retries.
     *
     * @param  array<int, InboundMessage>  $messages
     */
    private function resumePoint(array $messages, ?CarbonInterface $since): ?CarbonImmutable
    {
        $latest = null;

        foreach ($messages as $message) {
            if ($message->receivedAt !== null && ($latest === null || $message->receivedAt->greaterThan($latest))) {
                $latest = $message->receivedAt;
            }
        }

        if ($latest === null) {
            return null;
        }

        $resume = $latest->subSecond();

        if ($since !== null && $resume->lessThanOrEqualTo($since)) {
            $resume = CarbonImmutable::instance($since)->addSecond();
        }

        // Eloquent stores the wall-clock time without converting zones.
        return $resume->setTimezone(now()->getTimezone());
    }
}

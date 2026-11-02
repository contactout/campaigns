<?php

namespace App\Jobs\Campaigns;

use App\Actions\Mail\ProcessInboundMessage;
use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Models\MailerConnection;
use Carbon\CarbonImmutable;
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
     * The cursor means "everything received before this has been processed".
     * It is kept in whole seconds, as the database stores it, and a
     * never-polled connection starts from when it was created: nothing older
     * can answer mail sent through it.
     *
     * The cursor only moves once every fetched message has been processed:
     *
     * - A complete batch moves it to when the fetch started, not when it
     *   finished, so mail that arrives during a slow poll is read again.
     * - A truncated batch moves it to the second of the newest message
     *   processed, so the next run re-reads that second and carries on.
     * - A truncated batch that never left the cursor's own second (more mail
     *   in one second than one run takes) drains that second without a limit
     *   and moves past it, so ties are neither skipped nor re-read forever.
     *
     * A failed fetch throws and leaves the cursor where it was. Processing is
     * idempotent, so re-reading overlap is safe.
     */
    public function handle(MailboxReader $reader, ProcessInboundMessage $process): void
    {
        $connection = $this->mailerConnection;
        $startedAt = now()->toImmutable()->startOfSecond();
        $since = ($connection->last_checked_at ?? $connection->created_at)?->toImmutable()->startOfSecond();

        $batch = $reader->fetch($connection, $since, self::MESSAGES_PER_RUN);
        $this->process($process, $batch->messages);

        if (! $batch->hasMore) {
            $this->advance($startedAt);

            return;
        }

        $latest = $this->latestSecond($batch->messages);

        if ($latest === null) {
            return;
        }

        if ($since === null || $latest->greaterThan($since)) {
            $this->advance($latest);

            return;
        }

        $boundaryEnd = $since->addSecond();
        $boundary = $reader->fetch($connection, $since, null, $boundaryEnd);
        $this->process($process, $boundary->messages);

        $this->advance($boundaryEnd);
    }

    /**
     * Process each fetched message for the connection.
     *
     * @param  array<int, InboundMessage>  $messages
     */
    private function process(ProcessInboundMessage $process, array $messages): void
    {
        foreach ($messages as $message) {
            $process->handle($this->mailerConnection, $message);
        }
    }

    /**
     * Find the whole second the newest message was received in.
     *
     * Returns null when no message carried a received time, so the cursor
     * stays put and the next run retries.
     *
     * @param  array<int, InboundMessage>  $messages
     */
    private function latestSecond(array $messages): ?CarbonImmutable
    {
        $latest = null;

        foreach ($messages as $message) {
            if ($message->receivedAt !== null && ($latest === null || $message->receivedAt->greaterThan($latest))) {
                $latest = $message->receivedAt;
            }
        }

        return $latest?->startOfSecond();
    }

    /**
     * Save the new cursor.
     *
     * Eloquent stores the wall-clock time without converting zones, so the
     * cursor is put in the application's zone first.
     */
    private function advance(CarbonImmutable $cursor): void
    {
        $this->mailerConnection->last_checked_at = $cursor->setTimezone(now()->getTimezone());
        $this->mailerConnection->save();
    }
}

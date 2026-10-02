<?php

namespace App\Jobs\Campaigns;

use App\Contracts\Mail\CampaignMailer;
use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionStatus;
use App\Enums\RecipientStatus;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Models\Unsubscribe;
use App\Services\Mail\CampaignBodyBuilder;
use App\Services\Mail\CampaignStepScheduler;
use App\Services\Mail\PlaceholderRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * Failures are recorded on the email and connection instead of relying on
     * queue retries.
     */
    public int $tries = 1;

    /**
     * Seconds the per-email uniqueness lock is held if the job never runs.
     *
     * `campaigns:dispatch-due` re-selects every due email each minute, so a
     * backed-up queue would otherwise queue the same email many times over.
     */
    public int $uniqueFor = 3600;

    /**
     * Seconds the worker may spend on this job.
     *
     * Set explicitly so it stays below {@see self::LOCK_SECONDS}: the send lock
     * must outlive the job that holds it.
     */
    public int $timeout = 120;

    /**
     * Seconds the send lock is held while the email is being sent.
     *
     * Must outlast {@see self::timeout} so a slow send is never joined by a
     * second worker.
     */
    private const int LOCK_SECONDS = 300;

    /**
     * Minutes to wait before retrying an email the connection is throttling.
     */
    private const int RATE_LIMIT_RETRY_MINUTES = 15;

    /**
     * Cache key prefix for the per-email send lock.
     */
    private const string LOCK_PREFIX = 'campaign-email-send:';

    /**
     * Create a new job instance.
     */
    public function __construct(public CampaignEmail $email) {}

    /**
     * Allow only one queued job per campaign email.
     */
    public function uniqueId(): string
    {
        return (string) $this->email->id;
    }

    /**
     * Send the campaign email and advance the recipient to the next step.
     */
    public function handle(
        CampaignStepScheduler $scheduler,
        PlaceholderRenderer $renderer,
        CampaignBodyBuilder $bodyBuilder,
        CampaignMailer $mailer,
    ): void {
        // Two workers must not send the same email. Uniqueness only guards
        // dispatch, and its lock can expire while a job waits in a backlog, so
        // the send itself takes a lock keyed on the email.
        $lock = Cache::lock(self::LOCK_PREFIX.$this->email->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            // Leave the email scheduled; the next dispatch cycle picks it up
            // once the worker holding the lock is done.
            return;
        }

        try {
            $this->send($scheduler, $renderer, $bodyBuilder, $mailer);
        } finally {
            $lock->release();
        }
    }

    /**
     * Send the email once the caller holds its lock.
     */
    private function send(
        CampaignStepScheduler $scheduler,
        PlaceholderRenderer $renderer,
        CampaignBodyBuilder $bodyBuilder,
        CampaignMailer $mailer,
    ): void {
        // Re-read the row: the job may have sat in the queue while the email was
        // sent, failed, or pushed back by a rate limit.
        $email = CampaignEmail::query()->find($this->email->id);

        if (! $email instanceof CampaignEmail) {
            return;
        }

        $email->load([
            'campaign.mailerConnection',
            'step',
            'recipient.contact.emailIdentity',
            'recipient.contact.phoneIdentity',
            'recipient.contact.properties',
            'mailerConnection',
        ]);

        if (! $this->isDue($email)) {
            return;
        }

        if ($email->campaign->status !== CampaignStatus::Active) {
            return;
        }

        $to = (string) ($email->recipient->contact->email() ?? '');

        if ($to === '' || $this->isSuppressed($email, $to)) {
            $this->markFailed($email);

            return;
        }

        $connection = $email->mailerConnection ?? $email->campaign->mailerConnection;

        if (! $connection instanceof MailerConnection || $connection->status !== MailerConnectionStatus::Active) {
            $this->markFailed($email);

            return;
        }

        $this->refreshSendingWindow($connection);

        if ($this->isRateLimited($connection)) {
            $email->scheduled_at = now()->addMinutes(self::RATE_LIMIT_RETRY_MINUTES);
            $email->save();

            // When the connection may try again, not when it was throttled.
            $connection->rate_limit_expired_at = $email->scheduled_at;
            $connection->save();

            return;
        }

        $subject = $renderer->render($email->step->subject, $email->recipient);
        $html = $renderer->render($email->step->body, $email->recipient);
        $html = $bodyBuilder->build($email, $html);

        try {
            $result = $mailer->send($connection, $to, $subject, $html, $this->unsubscribeHeaders($email));
        } catch (Throwable $exception) {
            $this->markFailed($email);
            $this->recordConnectionFailure($connection, $exception);

            return;
        }

        $email->status = EmailStatus::Sent;
        $email->dispatched_at = now();

        if ($result->messageId !== null && $result->messageId !== '') {
            $email->message_id = $result->messageId;
        }

        if ($result->threadId !== null && $result->threadId !== '') {
            $email->thread_id = $result->threadId;
        }

        $email->save();

        $email->recipient->last_delivered_at = now();
        $email->recipient->save();

        $connection->increment('sent_count');

        $scheduler->scheduleNextStep($email);
    }

    /**
     * Determine whether the email is still waiting to be sent.
     *
     * A queued job becomes stale when the email was already sent, failed, or
     * pushed back by a rate limit, so it must not send on the strength of its
     * payload alone.
     */
    private function isDue(CampaignEmail $email): bool
    {
        if ($email->status !== EmailStatus::Scheduled) {
            return false;
        }

        return $email->scheduled_at === null || ! $email->scheduled_at->isFuture();
    }

    /**
     * Build the RFC 8058 one-click unsubscribe headers for the email.
     *
     * @return array<string, string>
     */
    private function unsubscribeHeaders(CampaignEmail $email): array
    {
        $url = URL::signedRoute('unsubscribe.store', ['recipient' => $email->recipient_id]);

        return [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }

    /**
     * Start a new sending day once the previous window has rolled over.
     *
     * `sent_count` is a per-UTC-day counter compared against `sending_limit`, so
     * without this a connection that reached its limit would stay throttled for
     * good. The window lives on the connection rather than in a scheduled
     * command, so the limit lifts even when the scheduler container is down.
     */
    private function refreshSendingWindow(MailerConnection $connection): void
    {
        if ($connection->sending_limit === null) {
            return;
        }

        if ($connection->sending_limit_refreshed_at !== null
            && $connection->sending_limit_refreshed_at->isFuture()) {
            return;
        }

        $connection->sent_count = 0;
        $connection->sending_limit_refreshed_at = CarbonImmutable::today('UTC')->addDay();
        $connection->save();
    }

    /**
     * Determine whether the connection has hit its sending limit.
     */
    private function isRateLimited(MailerConnection $connection): bool
    {
        return $connection->sending_limit !== null
            && $connection->sent_count >= $connection->sending_limit;
    }

    /**
     * Determine whether the recipient should no longer receive campaign email.
     *
     * A recipient is skipped when it carries a terminal status (unsubscribed,
     * replied or bounced) or the team holds an unsubscribe record for its email
     * address.
     */
    private function isSuppressed(CampaignEmail $email, string $to): bool
    {
        if (in_array($email->recipient->status, [
            RecipientStatus::Unsubscribed,
            RecipientStatus::Replied,
            RecipientStatus::Bounced,
        ], true)) {
            return true;
        }

        return Unsubscribe::query()
            ->where('team_id', $email->campaign->team_id)
            ->where('email', $to)
            ->exists();
    }

    /**
     * Mark the email as failed.
     */
    private function markFailed(CampaignEmail $email): void
    {
        $email->status = EmailStatus::Failed;
        $email->save();
    }

    /**
     * Deactivate the connection and record why it failed.
     */
    private function recordConnectionFailure(MailerConnection $connection, Throwable $exception): void
    {
        $connection->fill([
            'status' => MailerConnectionStatus::Deactivated,
            'exception_type' => $exception::class,
            'exception_data' => ['message' => $exception->getMessage()],
            'threw_at' => now(),
        ])->save();
    }
}

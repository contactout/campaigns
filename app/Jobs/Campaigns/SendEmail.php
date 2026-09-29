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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendEmail implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(public CampaignEmail $email) {}

    /**
     * Send the campaign email and advance the recipient to the next step.
     */
    public function handle(
        CampaignStepScheduler $scheduler,
        PlaceholderRenderer $renderer,
        CampaignBodyBuilder $bodyBuilder,
        CampaignMailer $mailer,
    ): void {
        $email = $this->email->load([
            'campaign.mailerConnection',
            'step',
            'recipient.contact.emailIdentity',
            'recipient.contact.phoneIdentity',
            'recipient.contact.properties',
            'mailerConnection',
        ]);

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

        if ($this->isRateLimited($connection)) {
            $email->scheduled_at = now()->addMinutes(15);
            $email->save();

            $connection->rate_limit_expired_at = now();
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

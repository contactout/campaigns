<?php

namespace App\Services\Mail;

use App\Data\CampaignSettings;
use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use Carbon\CarbonImmutable;

/**
 * Works out when campaign steps should send and materialises the queue rows.
 */
class CampaignStepScheduler
{
    public function __construct(private readonly SendingWindow $sendingWindow) {}

    /**
     * Compute the UTC send time for a step, relative to the recipient.
     *
     * The baseline is interpreted in the recipient's timezone, moved to the
     * start of that day, offset by the step's day, and set to the step's
     * configured time (defaulting to 09:00). Times already in the past fall
     * back to roughly now so they send on the next run, and the result is then
     * pushed onto the campaign's sending window.
     *
     * The step's `day` is counted from the recipient's baseline, which is the
     * later of the campaign start and the moment the recipient joined. A step
     * is never counted from the previous step's actual send time: `day` is
     * documented as an offset from the start of the sequence, and anchoring it
     * to the previous send would silently stretch a 0/3/7 sequence.
     */
    public function scheduledAtFor(Campaign $campaign, CampaignStep $step, ?Recipient $recipient = null): CarbonImmutable
    {
        $timezone = $this->timezoneFor($campaign, $recipient);

        $scheduled = $this->baselineFor($campaign, $recipient)
            ->setTimezone($timezone)
            ->startOfDay()
            ->addDays((int) ($step->day ?? 0))
            ->setTimeFromTimeString($step->time ?: '09:00:00')
            ->utc();

        if ($scheduled->isPast()) {
            $scheduled = CarbonImmutable::now()->addMinute();
        }

        return $this->sendingWindow->nextAllowedAt(
            $scheduled,
            $timezone,
            CampaignSettings::fromArray($campaign->settings),
        );
    }

    /**
     * Resolve the timezone a recipient's schedule is planned in.
     *
     * A recipient keeps the timezone it was added with; the campaign's timezone
     * is the fallback for recipients that have none.
     */
    public function timezoneFor(Campaign $campaign, ?Recipient $recipient = null): string
    {
        return $recipient?->timezone ?: $campaign->timezone;
    }

    /**
     * Resolve the instant a recipient's schedule is counted from.
     *
     * Contacts added to a running campaign join later than the campaign start,
     * so counting their steps from `started_at` puts every step in the past and
     * fires the whole sequence within minutes.
     */
    public function baselineFor(Campaign $campaign, ?Recipient $recipient = null): CarbonImmutable
    {
        $startedAt = $campaign->started_at ?? CarbonImmutable::now();

        $joinedAt = $recipient?->created_at === null
            ? $startedAt
            : CarbonImmutable::instance($recipient->created_at);

        return $joinedAt->isAfter($startedAt) ? $joinedAt : $startedAt;
    }

    /**
     * Create the first-step email for every eligible recipient of the campaign.
     *
     * Recipients already holding an email for the first step are skipped. Every
     * seed job scans all eligible recipients, so two enrollments arriving
     * together can both try to insert the same recipient and step; the unique
     * index on (recipient_id, campaign_step_id) settles which insert wins and
     * `createOrFirst` returns the row that landed.
     *
     * @return int The number of emails created.
     */
    public function scheduleFirstSteps(Campaign $campaign): int
    {
        $firstStep = $campaign->steps()->first();

        if ($firstStep === null) {
            return 0;
        }

        $recipients = $campaign->recipients()
            ->whereIn('status', [RecipientStatus::Active, RecipientStatus::Pending])
            ->get();

        $created = 0;

        foreach ($recipients as $recipient) {
            $email = CampaignEmail::query()->createOrFirst(
                [
                    'recipient_id' => $recipient->id,
                    'campaign_step_id' => $firstStep->id,
                ],
                [
                    'campaign_id' => $campaign->id,
                    'mailer_connection_id' => $campaign->mailer_connection_id,
                    'status' => EmailStatus::Scheduled,
                    'scheduled_at' => $this->scheduledAtFor($campaign, $firstStep, $recipient),
                ],
            );

            if ($email->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Queue the step following the given email, or complete the recipient.
     *
     * When no later step exists the recipient is marked completed and its
     * `last_delivered_at` timestamp is refreshed.
     */
    public function scheduleNextStep(CampaignEmail $email): void
    {
        $campaign = $email->campaign;
        $currentStep = $email->step;

        $nextStep = $campaign->steps()
            ->where('sequence', '>', $currentStep->sequence)
            ->orderBy('sequence')
            ->first();

        if ($nextStep === null) {
            $recipient = $email->recipient;
            $recipient->status = RecipientStatus::Completed;
            $recipient->last_delivered_at = now();
            $recipient->save();

            return;
        }

        CampaignEmail::query()->createOrFirst(
            [
                'recipient_id' => $email->recipient_id,
                'campaign_step_id' => $nextStep->id,
            ],
            [
                'campaign_id' => $campaign->id,
                'mailer_connection_id' => $email->mailer_connection_id ?? $campaign->mailer_connection_id,
                'status' => EmailStatus::Scheduled,
                'scheduled_at' => $this->scheduledAtFor($campaign, $nextStep, $email->recipient),
            ],
        );
    }
}

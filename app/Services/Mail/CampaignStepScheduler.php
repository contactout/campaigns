<?php

namespace App\Services\Mail;

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
    /**
     * Compute the UTC send time for a step relative to a recipient's baseline.
     *
     * The baseline is interpreted in the campaign timezone, moved to the start
     * of that day, offset by the step's day, and set to the step's configured
     * time (defaulting to 09:00). Times already in the past fall back to
     * roughly now so they send on the next run.
     *
     * The step's `day` is counted from the recipient's baseline, which is the
     * later of the campaign start and the moment the recipient joined. A step
     * is never counted from the previous step's actual send time: `day` is
     * documented as an offset from the start of the sequence, and anchoring it
     * to the previous send would silently stretch a 0/3/7 sequence.
     */
    public function scheduledAtFor(Campaign $campaign, CampaignStep $step, ?CarbonImmutable $baseline = null): CarbonImmutable
    {
        $baseline ??= $this->baselineFor($campaign);

        $scheduled = $baseline
            ->setTimezone($campaign->timezone)
            ->startOfDay()
            ->addDays((int) ($step->day ?? 0))
            ->setTimeFromTimeString($step->time ?: '09:00:00')
            ->utc();

        if ($scheduled->isPast()) {
            return CarbonImmutable::now()->addMinute();
        }

        return $scheduled;
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
     * Recipients already holding an email for the first step are skipped.
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
            if ($this->emailExists($recipient->id, $firstStep->id)) {
                continue;
            }

            CampaignEmail::create([
                'campaign_id' => $campaign->id,
                'campaign_step_id' => $firstStep->id,
                'recipient_id' => $recipient->id,
                'mailer_connection_id' => $campaign->mailer_connection_id,
                'status' => EmailStatus::Scheduled,
                'scheduled_at' => $this->scheduledAtFor($campaign, $firstStep, $this->baselineFor($campaign, $recipient)),
            ]);

            $created++;
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

        if ($this->emailExists($email->recipient_id, $nextStep->id)) {
            return;
        }

        CampaignEmail::create([
            'campaign_id' => $campaign->id,
            'campaign_step_id' => $nextStep->id,
            'recipient_id' => $email->recipient_id,
            'mailer_connection_id' => $email->mailer_connection_id ?? $campaign->mailer_connection_id,
            'status' => EmailStatus::Scheduled,
            'scheduled_at' => $this->scheduledAtFor($campaign, $nextStep, $this->baselineFor($campaign, $email->recipient)),
        ]);
    }

    /**
     * Determine whether an email already exists for the recipient/step pair.
     */
    private function emailExists(int $recipientId, int $stepId): bool
    {
        return CampaignEmail::query()
            ->where('recipient_id', $recipientId)
            ->where('campaign_step_id', $stepId)
            ->exists();
    }
}

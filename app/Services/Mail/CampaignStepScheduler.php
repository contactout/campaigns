<?php

namespace App\Services\Mail;

use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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
     * Every seed job scans all eligible recipients, so two enrollments arriving
     * together would otherwise each insert a first-step row for the same
     * recipient. The campaign row is locked for the duration so concurrent seed
     * jobs serialise and the later one sees the earlier one's rows.
     *
     * @return int The number of emails created.
     */
    public function scheduleFirstSteps(Campaign $campaign): int
    {
        $firstStep = $campaign->steps()->first();

        if ($firstStep === null) {
            return 0;
        }

        return DB::transaction(function () use ($campaign, $firstStep): int {
            $this->lockCampaign($campaign);

            $recipients = $campaign->recipients()
                ->whereIn('status', [RecipientStatus::Active, RecipientStatus::Pending])
                ->get();

            $created = 0;

            foreach ($recipients as $recipient) {
                $email = CampaignEmail::query()->firstOrCreate(
                    [
                        'recipient_id' => $recipient->id,
                        'campaign_step_id' => $firstStep->id,
                    ],
                    [
                        'campaign_id' => $campaign->id,
                        'mailer_connection_id' => $campaign->mailer_connection_id,
                        'status' => EmailStatus::Scheduled,
                        'scheduled_at' => $this->scheduledAtFor($campaign, $firstStep, $this->baselineFor($campaign, $recipient)),
                    ],
                );

                if ($email->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });
    }

    /**
     * Take the campaign row lock that serialises seeding.
     *
     * Deliberately used only for first-step seeding. `scheduleNextStep` runs on
     * every send, so locking the campaign row there would serialise every send
     * for a campaign against the others.
     */
    private function lockCampaign(Campaign $campaign): void
    {
        Campaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
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

        CampaignEmail::query()->firstOrCreate(
            [
                'recipient_id' => $email->recipient_id,
                'campaign_step_id' => $nextStep->id,
            ],
            [
                'campaign_id' => $campaign->id,
                'mailer_connection_id' => $email->mailer_connection_id ?? $campaign->mailer_connection_id,
                'status' => EmailStatus::Scheduled,
                'scheduled_at' => $this->scheduledAtFor($campaign, $nextStep, $this->baselineFor($campaign, $email->recipient)),
            ],
        );
    }
}

<?php

namespace App\Actions\Campaigns;

use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Services\Mail\CampaignStepScheduler;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCampaignStep
{
    public function __construct(private readonly CampaignStepScheduler $scheduler) {}

    /**
     * Delete the given campaign step and close any gaps in the sequence.
     *
     * Deleting a step cascades its emails. Emails that already went out carry
     * the recipient's history and thread ids, so a step with any is kept. A
     * recipient whose next email was on the step would otherwise never be
     * scheduled again, so it is moved on to the following step instead.
     *
     * An email already being sent for the step when it is deleted can still
     * lose its sent history. Recipients are never stranded by it, because
     * they are moved on to the following step here.
     *
     * @throws ValidationException when emails have already gone out for the step.
     */
    public function handle(CampaignStep $step): void
    {
        DB::transaction(function () use ($step): void {
            // Workers queueing a recipient's next step take the same lock (see
            // CampaignStepScheduler::scheduleNextStep()), so no email can be
            // added to this step between collecting its waiting recipients
            // and deleting it.
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($step->campaign_id);

            // Another deletion may have renumbered the sequence while this one
            // waited for the lock.
            $step->refresh();

            $hasHistory = $step->emails()
                ->whereNotIn('status', [EmailStatus::Pending, EmailStatus::Scheduled])
                ->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'campaign' => __('This step cannot be deleted because emails have already gone out for it.'),
                ]);
            }

            $this->advanceWaitingRecipients($campaign, $step);

            $step->delete();

            $this->normaliseSequence($campaign);
        });
    }

    /**
     * Move recipients waiting on the step on to the step that follows it.
     *
     * Recipients with no following step have finished the sequence and are
     * completed. Recipients that have left the sequence (replied, bounced,
     * unsubscribed, ...) are not advanced.
     */
    protected function advanceWaitingRecipients(Campaign $campaign, CampaignStep $step): void
    {
        $nextStep = $campaign->steps()
            ->where('sequence', '>', $step->sequence)
            ->first();

        $step->emails()
            ->with('recipient')
            ->get()
            ->each(function (CampaignEmail $email) use ($campaign, $nextStep): void {
                $recipient = $email->recipient;

                if (! in_array($recipient->status, [RecipientStatus::Active, RecipientStatus::Pending], true)) {
                    return;
                }

                if ($nextStep === null) {
                    $recipient->status = RecipientStatus::Completed;
                    $recipient->save();

                    return;
                }

                CampaignEmail::query()->createOrFirst(
                    [
                        'recipient_id' => $recipient->id,
                        'campaign_step_id' => $nextStep->id,
                    ],
                    [
                        'campaign_id' => $campaign->id,
                        'mailer_connection_id' => $email->mailer_connection_id ?? $campaign->mailer_connection_id,
                        'status' => EmailStatus::Scheduled,
                        'scheduled_at' => $this->scheduler->scheduledAtFor($campaign, $nextStep, $recipient),
                    ],
                );
            });
    }

    /**
     * Renumber the campaign steps starting from one.
     */
    protected function normaliseSequence(Campaign $campaign): void
    {
        $campaign->steps()->get()->each(function (CampaignStep $step, int $index): void {
            $step->update(['sequence' => $index + 1]);
        });
    }
}

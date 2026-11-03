<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Services\Mail\CampaignStepScheduler;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderCampaignSteps
{
    /**
     * Reorder the campaign steps to match the given id order (1..n).
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function handle(Campaign $campaign, array $orderedIds): void
    {
        DB::transaction(function () use ($campaign, $orderedIds): void {
            // Share the scheduler's lock so it never reads a partially reordered
            // sequence. Re-read the campaign in case it started after validation.
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($campaign->started_at !== null) {
                throw ValidationException::withMessages([
                    'steps' => __('Steps cannot be reordered after the campaign has started.'),
                ]);
            }

            foreach (array_values($orderedIds) as $index => $id) {
                CampaignStep::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereKey($id)
                    ->update(['sequence' => $index + 1]);
            }
        }, CampaignStepScheduler::TRANSACTION_ATTEMPTS);
    }
}

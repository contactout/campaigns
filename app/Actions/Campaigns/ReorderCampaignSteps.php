<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Support\Facades\DB;

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
            foreach (array_values($orderedIds) as $index => $id) {
                CampaignStep::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereKey($id)
                    ->update(['sequence' => $index + 1]);
            }
        });
    }
}

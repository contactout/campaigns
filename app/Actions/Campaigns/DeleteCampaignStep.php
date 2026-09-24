<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Support\Facades\DB;

class DeleteCampaignStep
{
    /**
     * Delete the given campaign step and close any gaps in the sequence.
     */
    public function handle(CampaignStep $step): void
    {
        DB::transaction(function () use ($step): void {
            $campaign = $step->campaign;

            $step->delete();

            $this->normaliseSequence($campaign);
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

<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Validation\ValidationException;

class StopCampaign
{
    /**
     * Stop the given campaign.
     *
     * @throws ValidationException when the campaign is not active.
     */
    public function handle(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Active) {
            throw ValidationException::withMessages([
                'campaign' => __('Only active campaigns can be stopped.'),
            ]);
        }

        $campaign->status = CampaignStatus::Stopped;
        $campaign->save();

        return $campaign;
    }
}

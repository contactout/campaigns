<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;

class ArchiveCampaign
{
    /**
     * Archive the given campaign.
     */
    public function handle(Campaign $campaign): Campaign
    {
        $campaign->status = CampaignStatus::Archived;
        $campaign->save();

        return $campaign;
    }
}

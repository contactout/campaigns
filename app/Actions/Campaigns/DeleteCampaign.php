<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;

class DeleteCampaign
{
    /**
     * Delete the given campaign.
     *
     * Steps, recipients and emails are removed via the foreign key cascade.
     */
    public function handle(Campaign $campaign): void
    {
        $campaign->delete();
    }
}

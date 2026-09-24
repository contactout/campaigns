<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;

class RemoveCampaignRecipients
{
    /**
     * Remove the given recipients from the campaign.
     *
     * @param  array<int, int|string>  $recipientIds
     * @return int the number of recipients removed
     */
    public function handle(Campaign $campaign, array $recipientIds): int
    {
        $recipientIds = array_values(array_unique(array_map(intval(...), $recipientIds)));

        if ($recipientIds === []) {
            return 0;
        }

        return $campaign->recipients()->whereKey($recipientIds)->delete();
    }
}

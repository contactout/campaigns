<?php

namespace App\Actions\Campaigns;

use App\Models\Recipient;

class RemoveCampaignRecipient
{
    /**
     * Remove a recipient from its campaign.
     */
    public function handle(Recipient $recipient): void
    {
        $recipient->delete();
    }
}

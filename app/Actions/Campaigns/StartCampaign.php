<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class StartCampaign
{
    /**
     * Start the given campaign.
     *
     * @throws ValidationException when the campaign cannot be started.
     */
    public function handle(Campaign $campaign): Campaign
    {
        if (! $campaign->canBeStarted()) {
            throw ValidationException::withMessages([
                'campaign' => __('Campaign needs at least one step before it can be started.'),
            ]);
        }

        $campaign->status = CampaignStatus::Active;
        $campaign->started_at = CarbonImmutable::now();
        $campaign->save();

        return $campaign;
    }
}

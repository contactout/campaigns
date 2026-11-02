<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Jobs\Campaigns\SeedCampaignEmails;
use App\Models\Campaign;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class StartCampaign
{
    /**
     * Start (or restart) the given campaign and queue its first-step emails.
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

        // Keep the original start on a restart: every step's `day` is counted
        // from it, so moving it would push the rest of the sequence back by
        // however long the campaign was stopped.
        $campaign->started_at ??= CarbonImmutable::now();
        $campaign->save();

        SeedCampaignEmails::dispatch($campaign);

        return $campaign;
    }
}

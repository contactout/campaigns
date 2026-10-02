<?php

namespace App\Actions\Campaigns;

use App\Data\CampaignSettings;
use App\Models\Campaign;

class UpdateCampaign
{
    /**
     * Update the given campaign.
     *
     * @param  array{name: string, timezone: string, mailer_connection_id?: int|null, settings?: array<string, mixed>}  $data
     */
    public function handle(Campaign $campaign, array $data): Campaign
    {
        $campaign->fill([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
        ]);

        if (array_key_exists('settings', $data)) {
            $campaign->settings = CampaignSettings::fromArray($data['settings'])->toArray();
        }

        if (array_key_exists('mailer_connection_id', $data)) {
            $campaign->mailer_connection_id = $data['mailer_connection_id'];
        }

        $campaign->save();

        return $campaign;
    }
}

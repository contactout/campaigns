<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;

class UpdateCampaign
{
    /**
     * Update the given campaign.
     *
     * @param  array{name: string, timezone: string, mailer_connection_id?: int|null}  $data
     */
    public function handle(Campaign $campaign, array $data): Campaign
    {
        $campaign->fill([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
        ]);

        if (array_key_exists('mailer_connection_id', $data)) {
            $campaign->mailer_connection_id = $data['mailer_connection_id'];
        }

        $campaign->save();

        return $campaign;
    }
}

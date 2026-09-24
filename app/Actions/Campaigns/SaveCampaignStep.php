<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignStep;

class SaveCampaignStep
{
    /**
     * Create a new campaign step, or update the given one.
     *
     * New steps are appended to the end of the campaign sequence.
     *
     * @param  array{subject: string, body: string, day: int, time?: string|null, is_threaded?: bool}  $data
     */
    public function handle(Campaign $campaign, array $data, ?CampaignStep $step = null): CampaignStep
    {
        $attributes = [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'day' => $data['day'],
            'time' => $data['time'] ?? null,
            'is_threaded' => $data['is_threaded'] ?? false,
        ];

        if ($step instanceof CampaignStep) {
            $step->fill($attributes)->save();

            return $step;
        }

        return $campaign->steps()->create([
            ...$attributes,
            'sequence' => ((int) $campaign->steps()->max('sequence')) + 1,
        ]);
    }
}

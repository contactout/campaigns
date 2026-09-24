<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Support\Facades\DB;

class DuplicateCampaign
{
    /**
     * Duplicate the given campaign and its steps as a fresh draft.
     */
    public function handle(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $copy = Campaign::query()->create([
                'team_id' => $campaign->team_id,
                'user_id' => $campaign->user_id,
                'name' => 'Copy of '.$campaign->name,
                'status' => CampaignStatus::Draft,
                'timezone' => $campaign->timezone,
                'mailer_connection_id' => $campaign->mailer_connection_id,
                'settings' => $campaign->settings,
                'started_at' => null,
                'interrupted_reason' => null,
            ]);

            $campaign->steps()->get()->each(function (CampaignStep $step, int $index) use ($copy): void {
                $copy->steps()->create([
                    'sequence' => $index + 1,
                    'subject' => $step->subject,
                    'body' => $step->body,
                    'day' => $step->day,
                    'time' => $step->time,
                    'is_threaded' => $step->is_threaded,
                    'setting' => $step->setting,
                ]);
            });

            return $copy;
        });
    }
}

<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;

class CreateCampaign
{
    /**
     * Create a draft campaign owned by the given team.
     *
     * @param  array{name: string, timezone: string, mailer_connection_id?: int|null}  $data
     */
    public function handle(Team $team, User $user, array $data): Campaign
    {
        return Campaign::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'],
            'status' => CampaignStatus::Draft,
            'timezone' => $data['timezone'],
            'mailer_connection_id' => $data['mailer_connection_id'] ?? null,
            'started_at' => null,
        ]);
    }
}

<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;

class CampaignPolicy
{
    /**
     * Determine whether the user can view any campaigns for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the campaign.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can create a campaign for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the campaign.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can delete the campaign.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can start the campaign.
     */
    public function start(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can stop the campaign.
     */
    public function stop(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can archive the campaign.
     */
    public function archive(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }

    /**
     * Determine whether the user can duplicate the campaign.
     */
    public function duplicate(User $user, Campaign $campaign): bool
    {
        return $user->belongsToTeam($campaign->team);
    }
}

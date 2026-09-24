<?php

namespace App\Policies;

use App\Models\Placeholder;
use App\Models\Team;
use App\Models\User;

class PlaceholderPolicy
{
    /**
     * Determine whether the user can view any placeholders for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the placeholder.
     */
    public function view(User $user, Placeholder $placeholder): bool
    {
        return $user->belongsToTeam($placeholder->team);
    }

    /**
     * Determine whether the user can create a placeholder for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the placeholder.
     */
    public function update(User $user, Placeholder $placeholder): bool
    {
        return $user->belongsToTeam($placeholder->team);
    }

    /**
     * Determine whether the user can delete the placeholder.
     */
    public function delete(User $user, Placeholder $placeholder): bool
    {
        return $user->belongsToTeam($placeholder->team);
    }
}

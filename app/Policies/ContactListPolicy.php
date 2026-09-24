<?php

namespace App\Policies;

use App\Models\ContactList;
use App\Models\Team;
use App\Models\User;

class ContactListPolicy
{
    /**
     * Determine whether the user can view any lists for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the list.
     */
    public function view(User $user, ContactList $list): bool
    {
        return $user->belongsToTeam($list->team);
    }

    /**
     * Determine whether the user can create a list for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the list.
     */
    public function update(User $user, ContactList $list): bool
    {
        return $user->belongsToTeam($list->team);
    }

    /**
     * Determine whether the user can delete the list.
     */
    public function delete(User $user, ContactList $list): bool
    {
        return $user->belongsToTeam($list->team);
    }
}

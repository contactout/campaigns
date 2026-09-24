<?php

namespace App\Policies;

use App\Models\ContactField;
use App\Models\Team;
use App\Models\User;

class ContactFieldPolicy
{
    /**
     * Determine whether the user can view any contact fields for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create a contact field for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the contact field.
     */
    public function update(User $user, ContactField $field): bool
    {
        return $user->belongsToTeam($field->team);
    }

    /**
     * Determine whether the user can delete the contact field.
     */
    public function delete(User $user, ContactField $field): bool
    {
        return $user->belongsToTeam($field->team);
    }
}

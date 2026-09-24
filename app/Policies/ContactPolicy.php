<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view any contacts for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team);
    }

    /**
     * Determine whether the user can create a contact for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team);
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team);
    }
}

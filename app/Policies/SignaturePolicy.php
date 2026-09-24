<?php

namespace App\Policies;

use App\Models\Signature;
use App\Models\Team;
use App\Models\User;

class SignaturePolicy
{
    /**
     * Determine whether the user can view any signatures for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the signature.
     */
    public function view(User $user, Signature $signature): bool
    {
        return $user->belongsToTeam($signature->team);
    }

    /**
     * Determine whether the user can create a signature for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the signature.
     */
    public function update(User $user, Signature $signature): bool
    {
        return $user->belongsToTeam($signature->team);
    }

    /**
     * Determine whether the user can delete the signature.
     */
    public function delete(User $user, Signature $signature): bool
    {
        return $user->belongsToTeam($signature->team);
    }
}

<?php

namespace App\Policies;

use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;

class MailerConnectionPolicy
{
    /**
     * Determine whether the user can view any mailer connections for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create a mailer connection for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the mailer connection.
     */
    public function update(User $user, MailerConnection $mailerConnection): bool
    {
        return $user->belongsToTeam($mailerConnection->team);
    }

    /**
     * Determine whether the user can delete the mailer connection.
     */
    public function delete(User $user, MailerConnection $mailerConnection): bool
    {
        return $user->belongsToTeam($mailerConnection->team);
    }
}

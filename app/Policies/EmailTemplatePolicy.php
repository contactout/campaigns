<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\User;

class EmailTemplatePolicy
{
    /**
     * Determine whether the user can view any templates for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the template.
     */
    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->belongsToTeam($template->team);
    }

    /**
     * Determine whether the user can create a template for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the template.
     */
    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->belongsToTeam($template->team);
    }

    /**
     * Determine whether the user can delete the template.
     */
    public function delete(User $user, EmailTemplate $template): bool
    {
        return $user->belongsToTeam($template->team);
    }
}

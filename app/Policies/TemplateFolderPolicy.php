<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\TemplateFolder;
use App\Models\User;

class TemplateFolderPolicy
{
    /**
     * Determine whether the user can view any folders for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the folder.
     */
    public function view(User $user, TemplateFolder $folder): bool
    {
        return $user->belongsToTeam($folder->team);
    }

    /**
     * Determine whether the user can create a folder for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the folder.
     */
    public function update(User $user, TemplateFolder $folder): bool
    {
        return $user->belongsToTeam($folder->team);
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, TemplateFolder $folder): bool
    {
        return $user->belongsToTeam($folder->team);
    }
}

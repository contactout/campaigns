<?php

namespace App\Actions\Templates;

use App\Models\Team;
use App\Models\TemplateFolder;
use App\Models\User;

class CreateTemplateFolder
{
    /**
     * Create a template folder owned by the given team.
     */
    public function handle(Team $team, User $user, string $name): TemplateFolder
    {
        return TemplateFolder::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $name,
        ]);
    }
}

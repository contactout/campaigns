<?php

namespace App\Actions\Contacts;

use App\Enums\PlaceholderType;
use App\Models\ContactField;
use App\Models\Team;
use App\Models\User;

class CreateContactField
{
    /**
     * Create a custom contact field owned by the given team.
     *
     * @param  array{name: string, type: PlaceholderType|string, fallback?: string|null}  $data
     */
    public function handle(Team $team, User $user, array $data): ContactField
    {
        return $team->contactFields()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'fallback' => $data['fallback'] ?? null,
            'position' => (int) $team->contactFields()->max('position') + 1,
        ]);
    }
}

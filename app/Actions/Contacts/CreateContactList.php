<?php

namespace App\Actions\Contacts;

use App\Models\ContactList;
use App\Models\Team;
use App\Models\User;

class CreateContactList
{
    /**
     * Create a contact list owned by the given team.
     */
    public function handle(Team $team, User $user, string $name, ?bool $isDefault = null): ContactList
    {
        return $team->contactLists()->create([
            'user_id' => $user->id,
            'name' => $name,
            'is_default' => $isDefault ?? false,
        ]);
    }
}

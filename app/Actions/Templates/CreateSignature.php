<?php

namespace App\Actions\Templates;

use App\Models\Signature;
use App\Models\Team;
use App\Models\User;

class CreateSignature
{
    /**
     * Create a signature owned by the given team.
     *
     * The single-default rule is enforced by the Signature model.
     *
     * @param  array{name: string, body: string, is_default?: bool}  $data
     */
    public function handle(Team $team, User $user, array $data): Signature
    {
        return Signature::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'],
            'body' => $data['body'],
            'is_default' => $data['is_default'] ?? false,
        ]);
    }
}

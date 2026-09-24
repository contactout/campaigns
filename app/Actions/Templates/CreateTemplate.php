<?php

namespace App\Actions\Templates;

use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\User;

class CreateTemplate
{
    /**
     * Create an email template owned by the given team.
     *
     * @param  array{name: string, subject?: string|null, body: string, folder_id?: int|null, is_draft?: bool}  $data
     */
    public function handle(Team $team, User $user, array $data): EmailTemplate
    {
        return EmailTemplate::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'],
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'],
            'is_draft' => $data['is_draft'] ?? false,
        ]);
    }
}

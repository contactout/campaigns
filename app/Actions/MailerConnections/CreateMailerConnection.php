<?php

namespace App\Actions\MailerConnections;

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;

class CreateMailerConnection
{
    /**
     * Create a mailer connection owned by the given team.
     *
     * @param  array{name: string, settings: array<string, mixed>}  $data
     */
    public function handle(Team $team, User $user, array $data): MailerConnection
    {
        return $team->mailerConnections()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'mailer_type' => MailerType::Smtp,
            'smtp_setting' => $data['settings'],
            'status' => MailerConnectionStatus::Pending,
        ]);
    }
}

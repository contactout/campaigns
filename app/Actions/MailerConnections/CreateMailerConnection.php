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
     * @param  array{name: string, settings: array<string, mixed>, sending_limit: int|null, min_seconds_between_sends: int|null}  $data
     */
    public function handle(Team $team, User $user, array $data): MailerConnection
    {
        return $team->mailerConnections()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'mailer_type' => MailerType::Smtp,
            'smtp_setting' => $data['settings'],
            'sending_limit' => $data['sending_limit'],
            'min_seconds_between_sends' => $data['min_seconds_between_sends'],
            'status' => MailerConnectionStatus::Pending,
        ]);
    }
}

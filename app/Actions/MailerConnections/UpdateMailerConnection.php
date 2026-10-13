<?php

namespace App\Actions\MailerConnections;

use App\Models\MailerConnection;

class UpdateMailerConnection
{
    /**
     * Update a mailer connection, merging the new SMTP settings over the stored ones.
     *
     * A blank password is not present in the incoming settings, so the existing
     * credential is preserved unless a replacement is supplied.
     *
     * @param  array{name: string, settings: array<string, mixed>, sending_limit: int|null, min_seconds_between_sends: int|null}  $data
     */
    public function handle(MailerConnection $connection, array $data): MailerConnection
    {
        $settings = array_merge($connection->smtp_setting ?? [], $data['settings']);

        $connection->fill([
            'name' => $data['name'],
            'smtp_setting' => $settings,
            'sending_limit' => $data['sending_limit'],
            'min_seconds_between_sends' => $data['min_seconds_between_sends'],
        ])->save();

        return $connection;
    }
}

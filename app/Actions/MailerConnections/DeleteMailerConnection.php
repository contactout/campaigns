<?php

namespace App\Actions\MailerConnections;

use App\Models\MailerConnection;

class DeleteMailerConnection
{
    /**
     * Delete the given mailer connection.
     */
    public function handle(MailerConnection $connection): void
    {
        $connection->delete();
    }
}

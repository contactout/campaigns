<?php

namespace App\Actions\MailerConnections;

use App\Models\MailerConnection;

class UpdateMailerConnectionSignature
{
    /**
     * Assign the signature used by the connection, or clear it to use the team default.
     */
    public function handle(MailerConnection $connection, ?int $signatureId): MailerConnection
    {
        $connection->signature_id = $signatureId;
        $connection->save();

        return $connection;
    }
}

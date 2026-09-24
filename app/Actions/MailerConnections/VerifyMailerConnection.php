<?php

namespace App\Actions\MailerConnections;

use App\Enums\MailerConnectionStatus;
use App\Models\MailerConnection;
use App\Services\Mail\SmtpConnectionVerifier;
use RuntimeException;

class VerifyMailerConnection
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly SmtpConnectionVerifier $verifier) {}

    /**
     * Probe the connection and record the outcome on the model.
     *
     * @param  SmtpConnectionVerifier|null  $verifier  Optional override, primarily for callers outside the container.
     */
    public function handle(MailerConnection $connection, ?SmtpConnectionVerifier $verifier = null): MailerConnection
    {
        $verifier ??= $this->verifier;

        try {
            $verifier->verify($connection->smtp_setting ?? []);
        } catch (RuntimeException $exception) {
            $connection->fill([
                'status' => MailerConnectionStatus::Deactivated,
                'exception_type' => $exception::class,
                'exception_data' => ['message' => $exception->getMessage()],
                'threw_at' => now(),
            ])->save();

            return $connection;
        }

        $connection->fill([
            'status' => MailerConnectionStatus::Active,
            'exception_type' => null,
            'exception_data' => null,
            'threw_at' => null,
        ])->save();

        return $connection;
    }
}

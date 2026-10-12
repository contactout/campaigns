<?php

namespace App\Actions\MailerConnections;

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use InvalidArgumentException;

class CreateOAuthMailerConnection
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly ResumeDeferredEmails $resumeDeferredEmails) {}

    /**
     * Create or update an OAuth mailer connection for the given team.
     *
     * Reconnecting an existing connection reactivates it and resumes the
     * emails held back while it was inactive.
     *
     * @param  array{
     *     access_token: string,
     *     refresh_token: string|null,
     *     expires_at: string,
     *     scope: string|null,
     *     email: string,
     *     name: string|null,
     *     provider_user_id: string
     * }  $tokens
     */
    public function handle(Team $team, User $user, MailerType $mailerType, array $tokens): MailerConnection
    {
        if (! in_array($mailerType, [MailerType::Gmail, MailerType::Outlook], true)) {
            throw new InvalidArgumentException('OAuth mailer connections require Gmail or Outlook.');
        }

        $email = strtolower(trim($tokens['email']));
        $name = filled($tokens['name']) ? (string) $tokens['name'] : $email;

        $settings = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $tokens['expires_at'],
            'scope' => $tokens['scope'],
            'email' => $email,
            'name' => $name,
            'provider_user_id' => $tokens['provider_user_id'],
            'from_email' => $email,
            'from_name' => $name,
        ];

        $connection = $team->mailerConnections()
            ->where('mailer_type', $mailerType)
            ->get()
            ->first(function (MailerConnection $existing) use ($email): bool {
                $existingEmail = strtolower((string) (
                    ($existing->smtp_setting['email'] ?? null)
                    ?? ($existing->smtp_setting['from_email'] ?? '')
                ));

                return $existingEmail === $email;
            });

        if ($connection instanceof MailerConnection) {
            $connection->fill([
                'user_id' => $user->id,
                'name' => $name,
                'smtp_setting' => $settings,
                'status' => MailerConnectionStatus::Active,
                'exception_type' => null,
                'exception_data' => null,
                'threw_at' => null,
            ])->save();

            $this->resumeDeferredEmails->handle($connection);

            return $connection->refresh();
        }

        return $team->mailerConnections()->create([
            'user_id' => $user->id,
            'name' => $name,
            'mailer_type' => $mailerType,
            'smtp_setting' => $settings,
            'status' => MailerConnectionStatus::Active,
            'exception_type' => null,
            'exception_data' => null,
            'threw_at' => null,
        ]);
    }
}

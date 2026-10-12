<?php

namespace App\Actions\MailerConnections;

use App\Contracts\Mail\GmailApi;
use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\Mail\SmtpConnectionVerifier;
use App\Services\OAuth\MicrosoftOAuthClient;
use App\Services\OAuth\OAuthTokenManager;
use Throwable;

class VerifyMailerConnection
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly SmtpConnectionVerifier $verifier,
        private readonly OAuthTokenManager $tokens,
        private readonly GmailApi $gmail,
        private readonly MicrosoftOAuthClient $microsoft,
        private readonly ResumeDeferredEmails $resumeDeferredEmails,
    ) {}

    /**
     * Probe the connection and record the outcome on the model.
     *
     * A successful probe also resumes the emails held back while the
     * connection was inactive.
     *
     * @param  SmtpConnectionVerifier|null  $verifier  Optional override, primarily for callers outside the container.
     */
    public function handle(MailerConnection $connection, ?SmtpConnectionVerifier $verifier = null): MailerConnection
    {
        try {
            match ($connection->mailer_type) {
                MailerType::Smtp => ($verifier ?? $this->verifier)->verify($connection->smtp_setting ?? []),
                MailerType::Gmail => $this->verifyGmail($connection),
                MailerType::Outlook => $this->verifyOutlook($connection),
            };
        } catch (Throwable $exception) {
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

        $this->resumeDeferredEmails->handle($connection);

        return $connection;
    }

    /**
     * Verify Gmail credentials by fetching the user profile.
     */
    private function verifyGmail(MailerConnection $connection): void
    {
        $accessToken = $this->tokens->accessToken($connection);
        $this->gmail->getProfileEmail($accessToken);
    }

    /**
     * Verify Outlook credentials by fetching the Microsoft Graph profile.
     */
    private function verifyOutlook(MailerConnection $connection): void
    {
        $accessToken = $this->tokens->accessToken($connection);
        $this->microsoft->profile($accessToken);
    }
}

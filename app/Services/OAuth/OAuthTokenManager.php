<?php

namespace App\Services\OAuth;

use App\Enums\MailerType;
use App\Models\MailerConnection;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Ensures OAuth mailer connections have a usable access token.
 */
class OAuthTokenManager
{
    /**
     * Create a new token manager instance.
     */
    public function __construct(
        private readonly GoogleOAuthClient $google,
        private readonly MicrosoftOAuthClient $microsoft,
    ) {}

    /**
     * Return a valid access token, refreshing when it expires within 60 seconds.
     *
     * @throws RuntimeException When the connection has no token or refresh fails.
     */
    public function accessToken(MailerConnection $connection): string
    {
        $settings = $connection->smtp_setting ?? [];
        $accessToken = (string) ($settings['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Mailer connection is missing an access token.');
        }

        if (! $this->needsRefresh($settings['expires_at'] ?? null)) {
            return $accessToken;
        }

        $refreshToken = (string) ($settings['refresh_token'] ?? '');

        if ($refreshToken === '') {
            throw new RuntimeException('Mailer connection is missing a refresh token.');
        }

        $refreshed = match ($connection->mailer_type) {
            MailerType::Gmail => $this->google->refresh($refreshToken),
            MailerType::Outlook => $this->microsoft->refresh($refreshToken),
            default => throw new RuntimeException('Mailer connection does not support OAuth token refresh.'),
        };

        $connection->smtp_setting = array_merge($settings, [
            'access_token' => $refreshed['access_token'],
            'refresh_token' => $refreshed['refresh_token'] ?? $refreshToken,
            'expires_at' => $refreshed['expires_at'],
            'scope' => $refreshed['scope'] ?? ($settings['scope'] ?? null),
        ]);
        $connection->save();

        return $refreshed['access_token'];
    }

    /**
     * Determine whether the stored expiry is within 60 seconds of now.
     */
    private function needsRefresh(mixed $expiresAt): bool
    {
        if ($expiresAt === null || $expiresAt === '') {
            return true;
        }

        try {
            $expiry = CarbonImmutable::parse((string) $expiresAt);
        } catch (\Throwable) {
            return true;
        }

        return $expiry->lessThanOrEqualTo(CarbonImmutable::now()->addSeconds(60));
    }
}

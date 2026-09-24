<?php

namespace App\Services\OAuth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google OAuth2 client for Gmail mailer connections.
 */
class GoogleOAuthClient
{
    /**
     * Determine whether Google OAuth credentials are configured.
     */
    public function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Build the Google authorization URL for the given CSRF state.
     */
    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', config('services.google.scopes', [])),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    /**
     * Exchange an authorization code for tokens and the user's profile.
     *
     * @return array{
     *     access_token: string,
     *     refresh_token: string|null,
     *     expires_at: string,
     *     scope: string|null,
     *     email: string,
     *     name: string|null,
     *     provider_user_id: string
     * }
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google token exchange failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Google token exchange returned an invalid response.');
        }

        $profile = $this->userInfo((string) $payload['access_token']);

        return [
            'access_token' => (string) $payload['access_token'],
            'refresh_token' => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            'expires_at' => $this->expiresAt($payload['expires_in'] ?? null)->toIso8601String(),
            'scope' => isset($payload['scope']) ? (string) $payload['scope'] : null,
            'email' => $profile['email'],
            'name' => $profile['name'],
            'provider_user_id' => $profile['sub'],
        ];
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_at: string, scope: string|null}
     */
    public function refresh(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google token refresh failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Google token refresh returned an invalid response.');
        }

        return [
            'access_token' => (string) $payload['access_token'],
            'refresh_token' => isset($payload['refresh_token'])
                ? (string) $payload['refresh_token']
                : $refreshToken,
            'expires_at' => $this->expiresAt($payload['expires_in'] ?? null)->toIso8601String(),
            'scope' => isset($payload['scope']) ? (string) $payload['scope'] : null,
        ];
    }

    /**
     * Fetch the authenticated user's profile from Google userinfo.
     *
     * @return array{email: string, name: string|null, sub: string}
     */
    private function userInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $response->successful()) {
            throw new RuntimeException('Google userinfo request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['email']) || empty($payload['sub'])) {
            throw new RuntimeException('Google userinfo returned an invalid response.');
        }

        return [
            'email' => (string) $payload['email'],
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'sub' => (string) $payload['sub'],
        ];
    }

    /**
     * Convert an expires_in seconds value into an absolute timestamp.
     */
    private function expiresAt(mixed $expiresIn): CarbonImmutable
    {
        $seconds = is_numeric($expiresIn) ? (int) $expiresIn : 3600;

        return CarbonImmutable::now()->addSeconds($seconds);
    }
}

<?php

namespace App\Services\OAuth;

use App\Exceptions\Mail\MailerHttpException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft OAuth2 client for Outlook mailer connections.
 */
class MicrosoftOAuthClient
{
    /**
     * Determine whether Microsoft OAuth credentials are configured.
     */
    public function configured(): bool
    {
        return filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'));
    }

    /**
     * Build the Microsoft authorization URL for the given CSRF state.
     */
    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', config('services.microsoft.scopes', [])),
            'response_mode' => 'query',
            'state' => $state,
        ]);

        return $this->authority().'/oauth2/v2.0/authorize?'.$query;
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
        $response = Http::asForm()->post($this->authority().'/oauth2/v2.0/token', [
            'code' => $code,
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'redirect_uri' => config('services.microsoft.redirect'),
            'grant_type' => 'authorization_code',
            'scope' => implode(' ', config('services.microsoft.scopes', [])),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Microsoft token exchange failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Microsoft token exchange returned an invalid response.');
        }

        $profile = $this->profile((string) $payload['access_token']);

        return [
            'access_token' => (string) $payload['access_token'],
            'refresh_token' => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            'expires_at' => $this->expiresAt($payload['expires_in'] ?? null)->toIso8601String(),
            'scope' => isset($payload['scope']) ? (string) $payload['scope'] : null,
            'email' => $profile['email'],
            'name' => $profile['name'],
            'provider_user_id' => $profile['id'],
        ];
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_at: string, scope: string|null}
     */
    public function refresh(string $refreshToken): array
    {
        $response = Http::asForm()->post($this->authority().'/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => implode(' ', config('services.microsoft.scopes', [])),
        ]);

        if (! $response->successful()) {
            throw MailerHttpException::fromResponse('Microsoft token refresh failed.', $response);
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Microsoft token refresh returned an invalid response.');
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
     * Fetch the authenticated user's profile from Microsoft Graph.
     *
     * @return array{email: string, name: string|null, id: string}
     */
    public function profile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me');

        if (! $response->successful()) {
            throw new RuntimeException('Microsoft Graph profile request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['id'])) {
            throw new RuntimeException('Microsoft Graph profile returned an invalid response.');
        }

        $email = (string) ($payload['mail'] ?? $payload['userPrincipalName'] ?? '');

        if ($email === '') {
            throw new RuntimeException('Microsoft Graph profile did not include an email address.');
        }

        return [
            'email' => $email,
            'name' => isset($payload['displayName']) ? (string) $payload['displayName'] : null,
            'id' => (string) $payload['id'],
        ];
    }

    /**
     * Build the Microsoft authority base URL for the configured tenant.
     */
    private function authority(): string
    {
        $tenant = (string) config('services.microsoft.tenant', 'common');

        return 'https://login.microsoftonline.com/'.$tenant;
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

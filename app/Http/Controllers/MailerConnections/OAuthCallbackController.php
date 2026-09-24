<?php

namespace App\Http\Controllers\MailerConnections;

use App\Actions\MailerConnections\CreateOAuthMailerConnection;
use App\Enums\MailerType;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\OAuth\GoogleOAuthClient;
use App\Services\OAuth\MicrosoftOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class OAuthCallbackController extends Controller
{
    /**
     * Handle the Google OAuth callback.
     */
    public function google(
        Request $request,
        GoogleOAuthClient $google,
        CreateOAuthMailerConnection $create,
    ): RedirectResponse {
        return $this->handle($request, 'gmail', MailerType::Gmail, fn (string $code): array => $google->exchangeCode($code), $create);
    }

    /**
     * Handle the Microsoft OAuth callback.
     */
    public function microsoft(
        Request $request,
        MicrosoftOAuthClient $microsoft,
        CreateOAuthMailerConnection $create,
    ): RedirectResponse {
        return $this->handle($request, 'outlook', MailerType::Outlook, fn (string $code): array => $microsoft->exchangeCode($code), $create);
    }

    /**
     * Validate session state, exchange the code, and persist the connection.
     *
     * @param  callable(string): array{
     *     access_token: string,
     *     refresh_token: string|null,
     *     expires_at: string,
     *     scope: string|null,
     *     email: string,
     *     name: string|null,
     *     provider_user_id: string
     * }  $exchange
     */
    private function handle(
        Request $request,
        string $provider,
        MailerType $mailerType,
        callable $exchange,
        CreateOAuthMailerConnection $create,
    ): RedirectResponse {
        $session = $request->session()->pull('mailer_oauth');

        $team = $this->resolveTeam($session);

        if (! is_array($session)
            || ($session['provider'] ?? null) !== $provider
            || ($session['state'] ?? null) !== $request->query('state')
            || ! $team instanceof Team
        ) {
            return $this->errorRedirect($team, __('OAuth state validation failed.'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->errorRedirect($team, __('OAuth authorization was cancelled or incomplete.'));
        }

        $user = User::query()->find($session['user_id'] ?? null);

        if (! $user instanceof User || (int) $user->id !== (int) $request->user()->id) {
            return $this->errorRedirect($team, __('OAuth session did not match the signed-in user.'));
        }

        try {
            $tokens = $exchange($code);
            $create->handle($team, $user, $mailerType, $tokens);
        } catch (Throwable $exception) {
            return $this->errorRedirect($team, $exception->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Mailer connection connected.'),
        ]);

        return redirect()->route('mailer-connections.index', ['current_team' => $team->slug]);
    }

    /**
     * Resolve the team from the OAuth session payload when possible.
     */
    private function resolveTeam(mixed $session): ?Team
    {
        if (! is_array($session) || empty($session['team_id'])) {
            return null;
        }

        $team = Team::query()->whereKey((int) $session['team_id'])->first();

        return $team instanceof Team ? $team : null;
    }

    /**
     * Flash an error and redirect back to mailer connections when possible.
     */
    private function errorRedirect(?Team $team, string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        if ($team instanceof Team) {
            return redirect()->route('mailer-connections.index', ['current_team' => $team->slug]);
        }

        return redirect()->route('home');
    }
}

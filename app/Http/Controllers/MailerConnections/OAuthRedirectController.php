<?php

namespace App\Http\Controllers\MailerConnections;

use App\Http\Controllers\Controller;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Services\OAuth\GoogleOAuthClient;
use App\Services\OAuth\MicrosoftOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OAuthRedirectController extends Controller
{
    /**
     * Start the OAuth authorization flow for Gmail or Outlook.
     */
    public function __invoke(
        Request $request,
        Team $currentTeam,
        string $provider,
        GoogleOAuthClient $google,
        MicrosoftOAuthClient $microsoft,
    ): RedirectResponse {
        Gate::authorize('create', [MailerConnection::class, $currentTeam]);

        $client = match ($provider) {
            'gmail' => $google,
            'outlook' => $microsoft,
            default => abort(404),
        };

        if (! $client->configured()) {
            abort(404);
        }

        $state = Str::random(40);

        $request->session()->put('mailer_oauth', [
            'team_id' => $currentTeam->id,
            'user_id' => $request->user()->id,
            'provider' => $provider,
            'state' => $state,
        ]);

        return redirect()->away($client->authorizationUrl($state));
    }
}

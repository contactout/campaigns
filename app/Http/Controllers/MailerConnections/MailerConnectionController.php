<?php

namespace App\Http\Controllers\MailerConnections;

use App\Actions\MailerConnections\CreateMailerConnection;
use App\Actions\MailerConnections\DeleteMailerConnection;
use App\Actions\MailerConnections\UpdateMailerConnection;
use App\Actions\MailerConnections\VerifyMailerConnection;
use App\Enums\MailerConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MailerConnections\StoreMailerConnectionRequest;
use App\Http\Requests\MailerConnections\UpdateMailerConnectionRequest;
use App\Models\MailerConnection;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MailerConnectionController extends Controller
{
    /**
     * Display a listing of the team's mailer connections.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [MailerConnection::class, $currentTeam]);

        $connections = MailerConnection::query()
            ->forTeam($currentTeam->id)
            ->orderBy('name')
            ->get()
            ->map(fn (MailerConnection $connection): array => $this->connectionSummary($connection))
            ->values()
            ->all();

        return Inertia::render('mailer-connections/index', [
            'connections' => $connections,
            'can' => [
                'create' => $request->user()->can('create', [MailerConnection::class, $currentTeam]),
            ],
        ]);
    }

    /**
     * Store a newly created mailer connection.
     */
    public function store(StoreMailerConnectionRequest $request, Team $currentTeam, CreateMailerConnection $createMailerConnection): RedirectResponse
    {
        Gate::authorize('create', [MailerConnection::class, $currentTeam]);

        /** @var array{name: string} $data */
        $data = $request->validated();

        $createMailerConnection->handle($currentTeam, $request->user(), [
            'name' => $data['name'],
            'settings' => $request->settings(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailer connection created.')]);

        return back();
    }

    /**
     * Update the given mailer connection.
     */
    public function update(UpdateMailerConnectionRequest $request, Team $currentTeam, MailerConnection $mailerConnection, UpdateMailerConnection $updateMailerConnection): RedirectResponse
    {
        $mailerConnection = MailerConnection::forTeam($currentTeam->id)->findOrFail($mailerConnection->id);

        Gate::authorize('update', $mailerConnection);

        /** @var array{name: string} $data */
        $data = $request->validated();

        $updateMailerConnection->handle($mailerConnection, [
            'name' => $data['name'],
            'settings' => $request->settings(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailer connection updated.')]);

        return back();
    }

    /**
     * Delete the given mailer connection.
     */
    public function destroy(Team $currentTeam, MailerConnection $mailerConnection, DeleteMailerConnection $deleteMailerConnection): RedirectResponse
    {
        $mailerConnection = MailerConnection::forTeam($currentTeam->id)->findOrFail($mailerConnection->id);

        Gate::authorize('delete', $mailerConnection);

        $deleteMailerConnection->handle($mailerConnection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailer connection deleted.')]);

        return back();
    }

    /**
     * Verify the given mailer connection.
     */
    public function verify(Team $currentTeam, MailerConnection $mailerConnection, VerifyMailerConnection $verifyMailerConnection): RedirectResponse
    {
        $mailerConnection = MailerConnection::forTeam($currentTeam->id)->findOrFail($mailerConnection->id);

        Gate::authorize('update', $mailerConnection);

        $verifyMailerConnection->handle($mailerConnection);

        if ($mailerConnection->status === MailerConnectionStatus::Active) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailer connection verified.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $mailerConnection->exception_data['message'] ?? __('Unable to verify the mailer connection.'),
            ]);
        }

        return back();
    }

    /**
     * Map a mailer connection to the safe payload exposed to the client.
     *
     * SMTP credentials (including the password) are never serialized.
     *
     * @return array{id: int, name: string, mailer_type: string, mailer_type_label: string, host: string|null, port: int|null, username: string|null, encryption: string|null, from_email: string|null, from_name: string|null, status: string, status_label: string, sent_count: int, sending_limit: int|null, last_error: string|null, created_at: string|null}
     */
    protected function connectionSummary(MailerConnection $connection): array
    {
        $settings = $connection->smtp_setting ?? [];

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'mailer_type' => $connection->mailer_type->value,
            'mailer_type_label' => $connection->mailer_type->label(),
            'host' => $settings['host'] ?? null,
            'port' => $settings['port'] ?? null,
            'username' => $settings['username'] ?? null,
            'encryption' => $settings['encryption'] ?? null,
            'from_email' => $settings['from_email'] ?? null,
            'from_name' => $settings['from_name'] ?? null,
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
            'sent_count' => $connection->sent_count,
            'sending_limit' => $connection->sending_limit,
            'last_error' => $connection->exception_type,
            'created_at' => $connection->created_at?->toISOString(),
        ];
    }
}

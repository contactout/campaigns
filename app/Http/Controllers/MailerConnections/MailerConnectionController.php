<?php

namespace App\Http\Controllers\MailerConnections;

use App\Actions\MailerConnections\CreateMailerConnection;
use App\Actions\MailerConnections\DeleteMailerConnection;
use App\Actions\MailerConnections\UpdateMailerConnection;
use App\Actions\MailerConnections\UpdateMailerConnectionSignature;
use App\Actions\MailerConnections\VerifyMailerConnection;
use App\Enums\MailerConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MailerConnections\StoreMailerConnectionRequest;
use App\Http\Requests\MailerConnections\UpdateMailerConnectionRequest;
use App\Http\Requests\MailerConnections\UpdateMailerConnectionSignatureRequest;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Models\Signature;
use App\Models\Team;
use App\Services\OAuth\GoogleOAuthClient;
use App\Services\OAuth\MicrosoftOAuthClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
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
    public function index(
        Request $request,
        Team $currentTeam,
        GoogleOAuthClient $google,
        MicrosoftOAuthClient $microsoft,
    ): Response {
        Gate::authorize('viewAny', [MailerConnection::class, $currentTeam]);

        $connections = MailerConnection::query()
            ->forTeam($currentTeam->id)
            ->orderBy('name')
            ->get();

        $sentToday = $this->sentTodayByConnection($connections);

        $connectionPayload = $connections
            ->map(fn (MailerConnection $connection): array => [
                ...$this->connectionSummary($connection),
                'sent_today' => (int) ($sentToday[$connection->id] ?? 0),
                'display_status' => $connection->displayStatus()->value,
                'display_status_label' => $connection->displayStatus()->label(),
                'needs_attention' => $connection->needsAttention(),
            ])
            ->values()
            ->all();

        $signatures = Signature::query()
            ->forTeam($currentTeam->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Signature $signature): array => [
                'id' => $signature->id,
                'name' => $signature->name,
                'is_default' => $signature->is_default,
            ])
            ->values()
            ->all();

        return Inertia::render('mailer-connections/index', [
            'connections' => $connectionPayload,
            'signatures' => $signatures,
            'oauth' => [
                'gmail' => $google->configured(),
                'outlook' => $microsoft->configured(),
            ],
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
            ...$request->limits(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection created.')]);

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
            ...$request->limits(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection updated.')]);

        return back();
    }

    /**
     * Assign the signature used by the given mailer connection.
     */
    public function updateSignature(UpdateMailerConnectionSignatureRequest $request, Team $currentTeam, MailerConnection $mailerConnection, UpdateMailerConnectionSignature $updateSignature): RedirectResponse
    {
        $mailerConnection = MailerConnection::forTeam($currentTeam->id)->findOrFail($mailerConnection->id);

        Gate::authorize('update', $mailerConnection);

        $signatureId = $request->validated('signature_id');

        $updateSignature->handle($mailerConnection, $signatureId === null ? null : (int) $signatureId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signature updated.')]);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection deleted.')]);

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
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection verified.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $mailerConnection->exception_data['message'] ?? __('Unable to verify the connection.'),
            ]);
        }

        return back();
    }

    /**
     * Count today's dispatches per connection.
     *
     * One read-only aggregate, no schema change: the `Sent today` column needs
     * the emails this connection actually dispatched since 00:00 UTC, which the
     * stored `sent_count` counter only approximates (it resets lazily on the next
     * send and is not a send log). The day boundary is UTC because that is where
     * SendEmail resets the same counter; connections store no timezone.
     *
     * `campaign_emails` has no index on (mailer_connection_id, dispatched_at), so
     * the statement scans the day's dispatch range and groups in a temp table.
     * The phase report carries the query and its EXPLAIN.
     *
     * @param  Collection<int, MailerConnection>  $connections  Eloquent collection.
     * @return array<int, int> Connection id => dispatched email count.
     */
    protected function sentTodayByConnection(Collection $connections): array
    {
        if ($connections->isEmpty()) {
            return [];
        }

        return CampaignEmail::query()
            ->whereIn('mailer_connection_id', $connections->modelKeys())
            ->where('dispatched_at', '>=', CarbonImmutable::today('UTC'))
            ->groupBy('mailer_connection_id')
            ->selectRaw('mailer_connection_id, count(*) as sent_today')
            ->pluck('sent_today', 'mailer_connection_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Map a mailer connection to the safe payload exposed to the client.
     *
     * SMTP/IMAP credentials (including both passwords) are never serialized.
     *
     * @return array{id: int, name: string, signature_id: int|null, mailer_type: string, mailer_type_label: string, host: string|null, port: int|null, username: string|null, encryption: string|null, from_email: string|null, from_name: string|null, imap_host: string|null, imap_port: int|null, imap_username: string|null, imap_encryption: string|null, status: string, status_label: string, sent_count: int, sending_limit: int|null, min_seconds_between_sends: int|null, last_error: string|null, last_error_message: string|null, created_at: string|null}
     */
    protected function connectionSummary(MailerConnection $connection): array
    {
        $settings = $connection->smtp_setting ?? [];

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'signature_id' => $connection->signature_id,
            'mailer_type' => $connection->mailer_type->value,
            'mailer_type_label' => $connection->mailer_type->label(),
            'host' => $settings['host'] ?? null,
            'port' => $settings['port'] ?? null,
            'username' => $settings['username'] ?? null,
            'encryption' => $settings['encryption'] ?? null,
            'from_email' => $settings['from_email'] ?? $settings['email'] ?? null,
            'from_name' => $settings['from_name'] ?? $settings['name'] ?? null,
            'imap_host' => $settings['imap_host'] ?? null,
            'imap_port' => $settings['imap_port'] ?? null,
            'imap_username' => $settings['imap_username'] ?? null,
            'imap_encryption' => $settings['imap_encryption'] ?? null,
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
            'sent_count' => $connection->sent_count,
            'sending_limit' => $connection->sending_limit,
            'min_seconds_between_sends' => $connection->min_seconds_between_sends,
            'last_error' => $connection->exception_type,
            'last_error_message' => $this->exceptionMessage($connection),
            'created_at' => $connection->created_at?->toISOString(),
        ];
    }

    /**
     * Get the stored verification/runtime failure message, if any.
     *
     * The exception class alone ("TransportException") tells an operator nothing,
     * so the banner shows the message the job or the probe recorded.
     */
    protected function exceptionMessage(MailerConnection $connection): ?string
    {
        $message = $connection->exception_data['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}

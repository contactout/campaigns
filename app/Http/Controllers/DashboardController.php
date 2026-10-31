<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\MailerConnectionStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TemplateFolder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the team dashboard, including setup progress when a team is selected.
     */
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->valid()
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        $setup = $team === null ? null : [
            'has_connection' => MailerConnection::query()->forTeam($team->id)->exists(),
            'has_contact' => Contact::query()->forTeam($team->id)->exists(),
            'has_campaign' => Campaign::query()->forTeam($team->id)->exists(),
        ];

        $overview = $team === null ? null : $this->overview($team);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'setup' => $setup,
            'metrics' => $overview['metrics'] ?? null,
            'jumpBackIn' => $overview['jumpBackIn'] ?? null,
            'mailerConnections' => $overview['mailerConnections'] ?? [],
            'can' => [
                'create' => $team !== null && $request->user()->can('create', [Campaign::class, $team]),
            ],
        ]);
    }

    /**
     * Build the dashboard aggregates for the given team.
     *
     * The four cards and the four "Jump back in" rows only ever read counts that
     * already exist in the schema. The 30-day window matches the board's
     * "Emails sent · 30d" label and the deltas compare it with the 30 days
     * before it, so no number on the page is invented. Active campaigns have no
     * stored history, so that card carries no delta.
     *
     * Open and reply are cohort rates, not same-age rates: an email sent hours
     * ago has had far less time to be opened than one sent in the previous
     * window, so the current rate is structurally understated and the delta
     * leans negative even when nothing changed. Same-age cohorts would remove
     * the bias; this method keeps the simple cohort and records the limitation.
     *
     * "Inboxes" counts every connection row the team has. "Needs attention" is
     * a connection that is not Active or that has an exception recorded, so a
     * Deactivated or Disconnected row is never reported as healthy and a Pending
     * row is flagged until it can send.
     *
     * @return array{
     *     metrics: array{emails_sent: int, open_rate: float|null, reply_rate: float|null, active_campaigns: int, emails_sent_delta: float|null, open_rate_delta: float|null, reply_rate_delta: float|null},
     *     jumpBackIn: array{contacts: int, lists: int, active_campaigns: int, draft_campaigns: int, templates: int, template_folders: int, connections: int, connections_needing_attention: int},
     *     mailerConnections: array<int, array{id: int, name: string, status: string, status_label: string}>
     * }
     */
    private function overview(Team $team): array
    {
        $windowStart = CarbonImmutable::now()->subDays(30);
        $previousStart = $windowStart->subDays(30);

        // One query covers both windows for all three email metrics: campaign_emails
        // has no team column, so the campaign_id index narrows the scan to the team.
        $emailStats = CampaignEmail::query()
            ->whereIn('campaign_id', Campaign::query()->forTeam($team->id)->select('id'))
            ->where('dispatched_at', '>=', $previousStart)
            ->selectRaw('count(case when dispatched_at >= ? then 1 end) as current_sent', [$windowStart])
            ->selectRaw('count(case when dispatched_at >= ? and opened_at is not null then 1 end) as current_opened', [$windowStart])
            ->selectRaw('count(case when dispatched_at >= ? and replied_at is not null then 1 end) as current_replied', [$windowStart])
            ->selectRaw('count(case when dispatched_at < ? then 1 end) as previous_sent', [$windowStart])
            ->selectRaw('count(case when dispatched_at < ? and opened_at is not null then 1 end) as previous_opened', [$windowStart])
            ->selectRaw('count(case when dispatched_at < ? and replied_at is not null then 1 end) as previous_replied', [$windowStart])
            ->first()?->toArray() ?? [];

        $currentSent = (int) ($emailStats['current_sent'] ?? 0);
        $previousSent = (int) ($emailStats['previous_sent'] ?? 0);
        $openRate = $this->rate((int) ($emailStats['current_opened'] ?? 0), $currentSent);
        $replyRate = $this->rate((int) ($emailStats['current_replied'] ?? 0), $currentSent);
        $previousOpenRate = $this->rate((int) ($emailStats['previous_opened'] ?? 0), $previousSent);
        $previousReplyRate = $this->rate((int) ($emailStats['previous_replied'] ?? 0), $previousSent);

        $activeCampaigns = Campaign::query()->forTeam($team->id)->active()->count();
        $draftCampaigns = Campaign::query()
            ->forTeam($team->id)
            ->where('status', CampaignStatus::Draft)
            ->count();

        // Fetched once: the row count, the "needs attention" half and the create
        // modal's connection options all read the same collection.
        $connections = MailerConnection::query()
            ->forTeam($team->id)
            ->orderBy('name')
            ->get();

        return [
            'metrics' => [
                'emails_sent' => $currentSent,
                'open_rate' => $openRate,
                'reply_rate' => $replyRate,
                'active_campaigns' => $activeCampaigns,
                'emails_sent_delta' => $previousSent > 0
                    ? round(($currentSent - $previousSent) / $previousSent * 100, 1)
                    : null,
                'open_rate_delta' => $openRate !== null && $previousOpenRate !== null
                    ? round($openRate - $previousOpenRate, 1)
                    : null,
                'reply_rate_delta' => $replyRate !== null && $previousReplyRate !== null
                    ? round($replyRate - $previousReplyRate, 1)
                    : null,
            ],
            'jumpBackIn' => [
                'contacts' => Contact::query()->forTeam($team->id)->count(),
                'lists' => ContactList::query()->forTeam($team->id)->count(),
                'active_campaigns' => $activeCampaigns,
                'draft_campaigns' => $draftCampaigns,
                'templates' => EmailTemplate::query()->forTeam($team->id)->count(),
                'template_folders' => TemplateFolder::query()->forTeam($team->id)->count(),
                'connections' => $connections->count(),
                'connections_needing_attention' => $connections
                    ->filter(fn (MailerConnection $connection): bool => $connection->status !== MailerConnectionStatus::Active
                        || $connection->exception_type !== null)
                    ->count(),
            ],
            'mailerConnections' => $connections
                ->map(fn (MailerConnection $connection): array => [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'status' => $connection->status->value,
                    'status_label' => $connection->status->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Turn a part over a total into a percentage with one decimal.
     *
     * Returns null when there is nothing to divide by, so the page can show a gap
     * instead of a fabricated 0%.
     */
    private function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }
}

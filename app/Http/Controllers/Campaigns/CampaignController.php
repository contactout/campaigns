<?php

namespace App\Http\Controllers\Campaigns;

use App\Actions\Campaigns\ArchiveCampaign;
use App\Actions\Campaigns\CreateCampaign;
use App\Actions\Campaigns\DeleteCampaign;
use App\Actions\Campaigns\DuplicateCampaign;
use App\Actions\Campaigns\StartCampaign;
use App\Actions\Campaigns\StopCampaign;
use App\Actions\Campaigns\UpdateCampaign;
use App\Data\CampaignSettings;
use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\MailerConnectionDisplayStatus;
use App\Enums\RecipientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\LinkClick;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Signature;
use App\Models\Team;
use App\Models\Unsubscribe;
use App\Services\Mail\SuppressionList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Display a listing of the team's campaigns.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', [Campaign::class, $currentTeam]);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
        ]);

        $filters = [
            'q' => isset($validated['q']) ? (trim((string) $validated['q']) ?: null) : null,
            'status' => $validated['status'] ?? null,
        ];

        $campaigns = Campaign::query()
            ->forTeam($currentTeam->id)
            ->withCount(['steps', 'recipients'])
            ->when($filters['q'], fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Campaign $campaign): array => $this->campaignSummary($campaign));

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
            'statuses' => array_map(
                fn (CampaignStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                CampaignStatus::cases(),
            ),
            'can' => [
                'create' => $user->can('create', [Campaign::class, $currentTeam]),
            ],
            'mailerConnections' => MailerConnection::optionsForTeam($currentTeam->id),
        ]);
    }

    /**
     * Store a newly created campaign.
     */
    public function store(StoreCampaignRequest $request, Team $currentTeam, CreateCampaign $createCampaign): RedirectResponse
    {
        Gate::authorize('create', [Campaign::class, $currentTeam]);

        /** @var array{name: string, timezone: string, mailer_connection_id?: int|null} $data */
        $data = $request->validated();

        $campaign = $createCampaign->handle($currentTeam, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('campaigns.show', ['campaign' => $campaign]);
    }

    /**
     * Display the given campaign.
     */
    public function show(Request $request, Team $currentTeam, Campaign $campaign): Response
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('view', $campaign);

        $campaign->load(['steps', 'mailerConnection']);

        $validated = $request->validate([
            'recipients_q' => ['nullable', 'string', 'max:255'],
        ]);

        $recipientsSearch = isset($validated['recipients_q'])
            ? (trim((string) $validated['recipients_q']) ?: null)
            : null;

        $recipients = $campaign->recipients()
            ->when($recipientsSearch, fn (Builder $query, string $search): Builder => $query->whereHas(
                'contact',
                // Grouped, so the OR cannot escape the team predicate: the search
                // is not an existence oracle for another team's contacts.
                fn (Builder $contact): Builder => $contact
                    ->where('team_id', $currentTeam->id)
                    ->where(fn (Builder $match): Builder => $match
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('emailIdentity', fn (Builder $identity): Builder => $identity->where('normalized_value', 'like', "%{$search}%"))),
            ))
            ->latest()
            ->paginate(25, ['*'], 'recipients_page')
            ->withQueryString();

        // One grouped query for the page's recipients rather than one per row.
        $recipientIds = array_map(intval(...), $recipients->getCollection()->pluck('id')->all());
        $engagement = $this->recipientEngagement($campaign->id, $recipientIds);

        // Deleting a contact leaves its recipient rows behind, so the page
        // resolves the contacts itself, soft-deleted ones included, instead of
        // letting the relation return null and take the page down with it.
        // Scoped to the team: every other read on this page is, and a recipient
        // row for a foreign contact (an import, a copy across teams) would
        // otherwise leak its name and email here.
        $contacts = Contact::query()
            ->forTeam($currentTeam->id)
            ->withTrashed()
            ->with('emailIdentity')
            ->whereIn('id', array_map(intval(...), $recipients->getCollection()->pluck('contact_id')->all()))
            ->get()
            ->keyBy('id')
            ->all();

        $recipients->through(fn (Recipient $recipient): array => $this->recipientRow(
            $recipient,
            $contacts[$recipient->contact_id] ?? null,
            $engagement[$recipient->id] ?? [],
        ));

        $availableContacts = Contact::query()
            ->forTeam($currentTeam->id)
            ->whereNotIn('id', $campaign->recipients()->select('contact_id'))
            ->with('emailIdentity')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->emailIdentity?->normalized_value,
            ])
            ->values()
            ->all();

        $lists = ContactList::query()
            ->forTeam($currentTeam->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (ContactList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'contacts_count' => (int) $list->getAttribute('contacts_count'),
            ])
            ->values()
            ->all();

        $templates = EmailTemplate::query()
            ->forTeam($currentTeam->id)
            ->orderBy('name')
            ->get()
            ->map(fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'body' => $template->body,
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
                'body' => $signature->body,
                'is_default' => $signature->is_default,
            ])
            ->values()
            ->all();

        $stepMetrics = $this->stepMetrics($campaign->id);
        $doNotContact = $this->doNotContactRecipients($campaign->id);
        $recipientsCount = $campaign->recipients()->count();

        $placeholders = collect([
            ['name' => 'name', 'label' => 'Full name'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'phone', 'label' => 'Phone'],
        ])
            ->merge(
                $currentTeam->contactFields()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (ContactField $field): array => [
                        'name' => $field->name,
                        'label' => $field->name,
                    ]),
            )
            ->values()
            ->all();

        return Inertia::render('campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'timezone' => $campaign->timezone,
                'settings' => CampaignSettings::fromArray($campaign->settings)->toArray(),
                'mailer_connection_id' => $campaign->mailer_connection_id,
                'started_at' => $campaign->started_at?->toISOString(),
                'interrupted_reason' => $campaign->interrupted_reason,
                'created_at' => $campaign->created_at?->toISOString(),
                'updated_at' => $campaign->updated_at?->toISOString(),
            ],
            'connection' => $this->connectionSummary($campaign->mailerConnection),
            'steps' => $campaign->steps
                ->map(fn (CampaignStep $step): array => [
                    'id' => $step->id,
                    'sequence' => $step->sequence,
                    'subject' => $step->subject,
                    'body' => $step->body,
                    'day' => $step->day,
                    'time' => $step->time,
                    'is_threaded' => $step->is_threaded,
                    'setting' => $step->setting,
                    'metrics' => $stepMetrics[$step->id] ?? ['sent' => 0, 'opened' => 0, 'replied' => 0, 'opened_rate' => null, 'replied_rate' => null],
                ])
                ->values()
                ->all(),
            'stats' => [
                'steps_count' => $campaign->steps->count(),
                'recipients_count' => $recipientsCount,
                'emails_sent' => $campaign->emails()->whereNotNull('dispatched_at')->count(),
                'emails_failed' => $campaign->emails()->where('status', EmailStatus::Failed)->count(),
                'opened' => $campaign->emails()->whereNotNull('opened_at')->count(),
                'clicked' => LinkClick::query()
                    ->whereHas('campaignEmail', fn (Builder $query): Builder => $query->where('campaign_id', $campaign->id))
                    ->distinct()
                    ->count('campaign_email_id'),
                'unsubscribed' => Unsubscribe::query()->where('campaign_id', $campaign->id)->count(),
            ],
            'recipients' => $recipients,
            'recipientsSearch' => $recipientsSearch,
            'start' => $this->startState($campaign, $recipientsCount, $doNotContact),
            'availableContacts' => $availableContacts,
            'lists' => $lists,
            'templates' => $templates,
            'signatures' => $signatures,
            'placeholders' => $placeholders,
            'can' => [
                'update' => $request->user()->can('update', $campaign),
                'delete' => $request->user()->can('delete', $campaign),
                'start' => $request->user()->can('start', $campaign),
                'stop' => $request->user()->can('stop', $campaign),
                'archive' => $request->user()->can('archive', $campaign),
                'duplicate' => $request->user()->can('duplicate', $campaign),
            ],
            'mailerConnections' => MailerConnection::optionsForTeam($currentTeam->id),
        ]);
    }

    /**
     * Update the given campaign.
     */
    public function update(UpdateCampaignRequest $request, Team $currentTeam, Campaign $campaign, UpdateCampaign $updateCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('update', $campaign);

        /** @var array{name: string, timezone: string, mailer_connection_id?: int|null} $data */
        $data = $request->validated();

        $updateCampaign->handle($campaign, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('campaigns.show', ['campaign' => $campaign]);
    }

    /**
     * Delete the given campaign.
     */
    public function destroy(Team $currentTeam, Campaign $campaign, DeleteCampaign $deleteCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('delete', $campaign);

        $deleteCampaign->handle($campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('campaigns.index');
    }

    /**
     * Start the given campaign.
     */
    public function start(Team $currentTeam, Campaign $campaign, StartCampaign $startCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('start', $campaign);

        try {
            $startCampaign->handle($campaign);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign started.')]);

        return back();
    }

    /**
     * Stop the given campaign.
     */
    public function stop(Team $currentTeam, Campaign $campaign, StopCampaign $stopCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('stop', $campaign);

        try {
            $stopCampaign->handle($campaign);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign stopped.')]);

        return back();
    }

    /**
     * Archive the given campaign.
     */
    public function archive(Team $currentTeam, Campaign $campaign, ArchiveCampaign $archiveCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('archive', $campaign);

        $archiveCampaign->handle($campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign archived.')]);

        return back();
    }

    /**
     * Duplicate the given campaign.
     */
    public function duplicate(Team $currentTeam, Campaign $campaign, DuplicateCampaign $duplicateCampaign): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('duplicate', $campaign);

        $copy = $duplicateCampaign->handle($campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign duplicated.')]);

        return to_route('campaigns.show', ['campaign' => $copy]);
    }

    /**
     * Map the campaign's own connection to the fields the detail page shows.
     *
     * The smtp settings hold both passwords; only the sender address leaves here.
     *
     * @return array{id: int, name: string, from_email: string|null, status: string, status_label: string}|null
     */
    protected function connectionSummary(?MailerConnection $connection): ?array
    {
        if (! $connection instanceof MailerConnection) {
            return null;
        }

        $settings = $connection->smtp_setting ?? [];

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'from_email' => $settings['from_email'] ?? $settings['email'] ?? null,
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
        ];
    }

    /**
     * Sent, opened and replied counts per step for the campaign.
     *
     * `sent` counts dispatched emails only, so an opened or replied email is
     * always a subset of it and the percentages cannot exceed 100.
     *
     * @return array<int, array{sent: int, opened: int, replied: int, opened_rate: int|null, replied_rate: int|null}>
     */
    protected function stepMetrics(int $campaignId): array
    {
        return CampaignEmail::query()
            ->where('campaign_id', $campaignId)
            ->groupBy('campaign_step_id')
            ->selectRaw('campaign_step_id')
            ->selectRaw('count(case when dispatched_at is not null then 1 end) as sent_count')
            ->selectRaw('count(case when opened_at is not null then 1 end) as opened_count')
            ->selectRaw('count(case when replied_at is not null then 1 end) as replied_count')
            ->get()
            ->mapWithKeys(function (CampaignEmail $row): array {
                $sent = (int) $row->getAttribute('sent_count');
                $opened = (int) $row->getAttribute('opened_count');
                $replied = (int) $row->getAttribute('replied_count');

                return [(int) $row->getAttribute('campaign_step_id') => [
                    'sent' => $sent,
                    'opened' => $opened,
                    'replied' => $replied,
                    'opened_rate' => $sent > 0 ? (int) round($opened / $sent * 100) : null,
                    'replied_rate' => $sent > 0 ? (int) round($replied / $sent * 100) : null,
                ]];
            })
            ->all();
    }

    /**
     * Dispatched, opened, replied and bounced counts for the given recipients.
     *
     * @param  array<int, int>  $recipientIds
     * @return array<int, array{sent: int, opened: int, replied: int, bounced: int}>
     */
    protected function recipientEngagement(int $campaignId, array $recipientIds): array
    {
        if ($recipientIds === []) {
            return [];
        }

        return CampaignEmail::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('recipient_id', $recipientIds)
            ->groupBy('recipient_id')
            ->selectRaw('recipient_id')
            ->selectRaw('count(case when dispatched_at is not null then 1 end) as sent_count')
            ->selectRaw('count(case when opened_at is not null then 1 end) as opened_count')
            ->selectRaw('count(case when replied_at is not null then 1 end) as replied_count')
            ->selectRaw('count(case when status = ? then 1 end) as bounced_count', [EmailStatus::Bounced->value])
            ->get()
            ->mapWithKeys(fn (CampaignEmail $row): array => [(int) $row->getAttribute('recipient_id') => [
                'sent' => (int) $row->getAttribute('sent_count'),
                'opened' => (int) $row->getAttribute('opened_count'),
                'replied' => (int) $row->getAttribute('replied_count'),
                'bounced' => (int) $row->getAttribute('bounced_count'),
            ]])
            ->all();
    }

    /**
     * Count the campaign's recipients whose contact is flagged do-not-contact.
     *
     * This follows the app's own definition rather than the status column
     * alone: {@see SuppressionList::isBlocked()} also treats `do_not_contact_at`
     * as blocked, and the contact page reports "Do not contact" from that
     * column, so a contact with only the timestamp set is counted too.
     * Soft-deleted contacts are excluded, as everywhere else.
     */
    protected function doNotContactRecipients(int $campaignId): int
    {
        return Recipient::query()
            ->join('contacts', 'contacts.id', '=', 'recipients.contact_id')
            ->where('recipients.campaign_id', $campaignId)
            ->whereNull('contacts.deleted_at')
            ->where(function (Builder $query): void {
                $query
                    ->where('contacts.status', ContactStatus::DoNotContact->value)
                    ->orWhereNotNull('contacts.do_not_contact_at');
            })
            ->count();
    }

    /**
     * Map a recipient row, deriving the board's status vocabulary from data.
     *
     * `RecipientStatus` cannot express the board's Contacted / Opened / Not
     * contacted split; the campaign's own emails can. Terminal recipient states
     * win over engagement, so a replied contact never reads as merely opened.
     *
     * @param  array{sent?: int, opened?: int, replied?: int, bounced?: int}  $engagement
     * @return array{id: int, contact_id: int, name: string, email: string|null, status: string, status_label: string, display_status: string, display_status_label: string, created_at: string|null}
     */
    protected function recipientRow(Recipient $recipient, ?Contact $contact, array $engagement): array
    {
        $sent = $engagement['sent'] ?? 0;
        $opened = $engagement['opened'] ?? 0;
        $replied = $engagement['replied'] ?? 0;
        $bounced = $engagement['bounced'] ?? 0;

        [$displayStatus, $displayStatusLabel] = match (true) {
            $replied > 0 || $recipient->status === RecipientStatus::Replied => ['replied', 'Replied'],
            $bounced > 0 || $recipient->status === RecipientStatus::Bounced => ['bounced', 'Bounced'],
            $recipient->status === RecipientStatus::Unsubscribed => ['unsubscribed', 'Unsubscribed'],
            $opened > 0 => ['opened', 'Opened'],
            $sent > 0 => ['contacted', 'Contacted'],
            $recipient->status === RecipientStatus::Error => ['error', 'Error'],
            $recipient->status === RecipientStatus::Paused => ['paused', 'Paused'],
            $recipient->status === RecipientStatus::Archived => ['archived', 'Archived'],
            $recipient->status === RecipientStatus::Completed => ['completed', 'Completed'],
            default => ['not_contacted', 'Not contacted'],
        };

        return [
            'id' => $recipient->id,
            'contact_id' => $recipient->contact_id,
            'name' => $contact === null ? __('Deleted contact') : $contact->name,
            'email' => $contact?->emailIdentity?->normalized_value,
            'status' => $recipient->status->value,
            'status_label' => $recipient->status->label(),
            'display_status' => $displayStatus,
            'display_status_label' => $displayStatusLabel,
            'created_at' => $recipient->created_at?->toISOString(),
        ];
    }

    /**
     * Derive why the campaign cannot be started, and what will be skipped.
     *
     * Every reason comes from stored state; nothing is rendered unconditionally.
     * Only Draft and Stopped campaigns can be started, so only they report
     * reasons. `blocking` reasons disable Start. The do-not-contact line is a
     * warning: those recipients are suppressed at send time, not a reason to
     * refuse. An empty recipient list is deliberately not a blocker, because
     * the scheduler supports recipients joining a running campaign.
     *
     * @return array{blocked: bool, reasons: array<int, array{type: string, message: string, blocking: bool, step_id: int|null, step_sequence: int|null}>}
     */
    protected function startState(Campaign $campaign, int $recipientsCount, int $doNotContact): array
    {
        // Only a campaign that can actually be started gets a blocked state; an
        // Active or Completed campaign has no Start action to explain.
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Stopped], true)) {
            return ['blocked' => false, 'reasons' => []];
        }

        $reasons = [];

        if ($campaign->steps->isEmpty()) {
            $reasons[] = $this->startReason('steps', __('This campaign has no steps yet.'));
        }

        foreach ($campaign->steps as $step) {
            if (trim((string) $step->subject) !== '') {
                continue;
            }

            $reasons[] = $this->startReason(
                'step',
                __('Step :sequence (Day :day) has an empty subject', [
                    'sequence' => $step->sequence,
                    'day' => (int) ($step->day ?? 0),
                ]),
                $step->id,
                $step->sequence,
            );
        }

        $connection = $campaign->mailerConnection;

        if (! $connection instanceof MailerConnection) {
            $reasons[] = $this->startReason('connection', __('This campaign has no sending connection.'));
        } else {
            // Same derivation as the connections pill and the dashboard count, so
            // a row that reads Verified can never block a start, and one that reads
            // Failed always does.
            $displayStatus = $connection->displayStatus();

            if ($displayStatus === MailerConnectionDisplayStatus::Failed) {
                $reasons[] = $this->startReason('connection', __('The sending connection ":name" failed verification', ['name' => $connection->name]));
            } elseif ($displayStatus !== MailerConnectionDisplayStatus::Verified) {
                $reasons[] = $this->startReason('connection', __('The sending connection ":name" is :status', [
                    'name' => $connection->name,
                    'status' => mb_strtolower($displayStatus->label()),
                ]));
            }
        }

        if ($doNotContact > 0) {
            $reasons[] = $this->startReason('do_not_contact', trans_choice(
                '{1} :count recipient is marked Do not contact and will be skipped|[2,*] :count recipients are marked Do not contact and will be skipped',
                $doNotContact,
            ), blocking: false);
        }

        return [
            'blocked' => collect($reasons)->contains('blocking', true),
            'reasons' => $reasons,
        ];
    }

    /**
     * Build one start reason row.
     *
     * @return array{type: string, message: string, blocking: bool, step_id: int|null, step_sequence: int|null}
     */
    protected function startReason(string $type, string $message, ?int $stepId = null, ?int $stepSequence = null, bool $blocking = true): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'blocking' => $blocking,
            'step_id' => $stepId,
            'step_sequence' => $stepSequence,
        ];
    }

    /**
     * Map a campaign to the summary payload used by the index page.
     *
     * @return array{id: int, name: string, status: string, status_label: string, steps_count: int, recipients_count: int, started_at: string|null, created_at: string|null}
     */
    protected function campaignSummary(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'steps_count' => (int) $campaign->getAttribute('steps_count'),
            'recipients_count' => (int) $campaign->getAttribute('recipients_count'),
            'started_at' => $campaign->started_at?->toISOString(),
            'created_at' => $campaign->created_at?->toISOString(),
        ];
    }
}

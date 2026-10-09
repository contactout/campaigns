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
use App\Enums\EmailStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Models\Campaign;
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
            'mailerConnections' => $this->mailerConnectionOptions($currentTeam),
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

        $campaign->load('steps');

        $recipients = $campaign->recipients()
            ->with('contact.emailIdentity')
            ->latest()
            ->paginate(25, ['*'], 'recipients_page')
            ->withQueryString()
            ->through(fn (Recipient $recipient): array => [
                'id' => $recipient->id,
                'contact_id' => $recipient->contact_id,
                'name' => $recipient->contact->name,
                'email' => $recipient->contact->emailIdentity?->normalized_value,
                'status' => $recipient->status->value,
                'status_label' => $recipient->status->label(),
                'created_at' => $recipient->created_at?->toISOString(),
            ]);

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
                ])
                ->values()
                ->all(),
            'stats' => [
                'steps_count' => $campaign->steps->count(),
                'recipients_count' => $campaign->recipients()->count(),
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
            'mailerConnections' => $this->mailerConnectionOptions($currentTeam),
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
     * Map the team's mailer connections to the compact options payload used by campaign modals.
     *
     * @return array<int, array{id: int, name: string, status: string, status_label: string}>
     */
    protected function mailerConnectionOptions(Team $team): array
    {
        return MailerConnection::query()
            ->forTeam($team->id)
            ->orderBy('name')
            ->get()
            ->map(fn (MailerConnection $connection): array => [
                'id' => $connection->id,
                'name' => $connection->name,
                'status' => $connection->status->value,
                'status_label' => $connection->status->label(),
            ])
            ->values()
            ->all();
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

<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\UpdateContact;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\ContactList;
use App\Models\Recipient;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of the team's contacts.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', [Contact::class, $currentTeam]);

        $filters = [
            'q' => $request->string('q')->trim()->toString() ?: null,
            'list' => $request->integer('list') ?: null,
        ];

        $contacts = Contact::query()
            ->forTeam($currentTeam->id)
            ->withCount('lists')
            ->with('emailIdentity')
            ->when($filters['q'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('identities', fn (Builder $query) => $query
                            ->forType(ContactIdentityType::Email)
                            ->where('normalized_value', 'like', "%{$search}%"));
                });
            })
            ->when($filters['list'], fn (Builder $query, int $listId) => $query
                ->whereHas('lists', fn (Builder $query) => $query->whereKey($listId)))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $contact): array => $this->contactSummary($contact));

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'lists' => $currentTeam->contactLists()
                ->orderBy('name')
                ->get()
                ->map(fn (ContactList $list): array => [
                    'id' => $list->id,
                    'name' => $list->name,
                ])
                ->all(),
            'can' => [
                'create' => $user->can('create', [Contact::class, $currentTeam]),
            ],
            'statuses' => array_map(
                fn (ContactStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                ContactStatus::cases(),
            ),
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(StoreContactRequest $request, Team $currentTeam, CreateContact $createContact): RedirectResponse
    {
        Gate::authorize('create', [Contact::class, $currentTeam]);

        /** @var array{name: string, email: string, phone?: string|null, timezone?: string|null, status?: string|null, lists?: array<int, int|string>|null, source?: string|null} $data */
        $data = $request->validated();

        $contact = $createContact->handle($currentTeam, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.show', ['contact' => $contact]);
    }

    /**
     * Display the given contact.
     */
    public function show(Request $request, Team $currentTeam, Contact $contact): Response
    {
        $contact = Contact::forTeam($currentTeam->id)->findOrFail($contact->id);

        Gate::authorize('view', $contact);

        $contact->load(['emailIdentity', 'identities', 'lists']);

        $phoneIdentity = $contact->identities
            ->first(fn (ContactIdentity $identity): bool => $identity->identity_type === ContactIdentityType::Phone);

        $recipients = $contact->recipients()
            ->with('campaign')
            ->latest()
            ->get()
            ->map(fn (Recipient $recipient): array => [
                'id' => $recipient->id,
                'campaign' => $recipient->campaign->name,
                'status' => $recipient->status->value,
                'status_label' => $recipient->status->label(),
            ])
            ->values()
            ->all();

        return Inertia::render('contacts/show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->emailIdentity?->normalized_value,
                'phone' => $phoneIdentity?->normalized_value,
                'status' => $contact->status->value,
                'status_label' => $contact->status->label(),
                'timezone' => $contact->timezone,
                'source' => $contact->source,
                'created_at' => $contact->created_at?->toISOString(),
                'updated_at' => $contact->updated_at?->toISOString(),
                'do_not_contact' => $contact->do_not_contact_at !== null,
            ],
            'lists' => $contact->lists
                ->map(fn (ContactList $list): array => [
                    'id' => $list->id,
                    'name' => $list->name,
                ])
                ->values()
                ->all(),
            'recipients' => $recipients,
        ]);
    }

    /**
     * Update the given contact.
     */
    public function update(UpdateContactRequest $request, Team $currentTeam, Contact $contact, UpdateContact $updateContact): RedirectResponse
    {
        $contact = Contact::forTeam($currentTeam->id)->findOrFail($contact->id);

        Gate::authorize('update', $contact);

        /** @var array{name: string, email: string, phone?: string|null, timezone?: string|null, status?: string|null, lists?: array<int, int|string>|null, source?: string|null} $data */
        $data = $request->validated();

        $updateContact->handle($contact, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('contacts.show', ['contact' => $contact]);
    }

    /**
     * Delete the given contact.
     */
    public function destroy(Team $currentTeam, Contact $contact, DeleteContact $deleteContact): RedirectResponse
    {
        $contact = Contact::forTeam($currentTeam->id)->findOrFail($contact->id);

        Gate::authorize('delete', $contact);

        $deleteContact->handle($contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('contacts.index');
    }

    /**
     * Map a contact to the summary payload used by the index and list pages.
     *
     * @return array{id: int, name: string, email: string|null, status: string, status_label: string, lists_count: int, created_at: string|null}
     */
    protected function contactSummary(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->emailIdentity?->normalized_value,
            'status' => $contact->status->value,
            'status_label' => $contact->status->label(),
            'lists_count' => (int) $contact->getAttribute('lists_count'),
            'created_at' => $contact->created_at?->toISOString(),
        ];
    }
}

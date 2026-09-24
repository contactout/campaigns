<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\AttachContactsToList;
use App\Actions\Contacts\CreateContactList;
use App\Actions\Contacts\DeleteContactList;
use App\Actions\Contacts\DetachContactFromList;
use App\Actions\Contacts\UpdateContactList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactListRequest;
use App\Http\Requests\Contacts\UpdateContactListRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactListController extends Controller
{
    /**
     * Display a listing of the team's contact lists.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [ContactList::class, $currentTeam]);

        $lists = ContactList::query()
            ->forTeam($currentTeam->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (ContactList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'is_default' => $list->is_default,
                'contacts_count' => (int) $list->getAttribute('contacts_count'),
                'created_at' => $list->created_at?->toISOString(),
            ])
            ->all();

        return Inertia::render('lists/index', [
            'lists' => $lists,
            'can' => [
                'create' => $request->user()->can('create', [ContactList::class, $currentTeam]),
            ],
        ]);
    }

    /**
     * Store a newly created contact list.
     */
    public function store(StoreContactListRequest $request, Team $currentTeam, CreateContactList $createContactList): RedirectResponse
    {
        Gate::authorize('create', [ContactList::class, $currentTeam]);

        $createContactList->handle(
            $currentTeam,
            $request->user(),
            $request->validated('name'),
            $request->boolean('is_default'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('List created.')]);

        return to_route('lists.index');
    }

    /**
     * Display the given contact list with its contacts.
     */
    public function show(Request $request, Team $currentTeam, ContactList $list): Response
    {
        $list = ContactList::forTeam($currentTeam->id)->findOrFail($list->id);

        Gate::authorize('view', $list);

        $contacts = $list->contacts()
            ->withCount('lists')
            ->with('emailIdentity')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $contact): array => $this->contactSummary($contact));

        $availableContacts = Contact::query()
            ->forTeam($currentTeam->id)
            ->whereDoesntHave('lists', fn ($query) => $query->whereKey($list->id))
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

        return Inertia::render('lists/show', [
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'is_default' => $list->is_default,
            ],
            'contacts' => $contacts,
            'availableContacts' => $availableContacts,
            'can' => [
                'update' => $request->user()->can('update', $list),
                'delete' => $request->user()->can('delete', $list),
            ],
        ]);
    }

    /**
     * Update the given contact list.
     */
    public function update(UpdateContactListRequest $request, Team $currentTeam, ContactList $list, UpdateContactList $updateContactList): RedirectResponse
    {
        $list = ContactList::forTeam($currentTeam->id)->findOrFail($list->id);

        Gate::authorize('update', $list);

        $updateContactList->handle(
            $list,
            $request->validated('name'),
            $request->has('is_default') ? $request->boolean('is_default') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('List updated.')]);

        return to_route('lists.show', ['list' => $list]);
    }

    /**
     * Delete the given contact list.
     */
    public function destroy(Team $currentTeam, ContactList $list, DeleteContactList $deleteContactList): RedirectResponse
    {
        $list = ContactList::forTeam($currentTeam->id)->findOrFail($list->id);

        Gate::authorize('delete', $list);

        $deleteContactList->handle($list);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('List deleted.')]);

        return to_route('lists.index');
    }

    /**
     * Attach one or more team contacts to the given list.
     */
    public function attachContacts(Request $request, Team $currentTeam, ContactList $list, AttachContactsToList $attachContacts): RedirectResponse
    {
        $list = ContactList::forTeam($currentTeam->id)->findOrFail($list->id);

        Gate::authorize('update', $list);

        $validated = $request->validate([
            'contacts' => ['required', 'array'],
            'contacts.*' => [
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('team_id', $currentTeam->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        /** @var array<int, int|string> $contactIds */
        $contactIds = $validated['contacts'];

        $attachContacts->handle($list, array_map(intval(...), $contactIds));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contacts added to list.')]);

        return back();
    }

    /**
     * Detach a contact from the given list.
     */
    public function detachContact(Team $currentTeam, ContactList $list, Contact $contact, DetachContactFromList $detachContact): RedirectResponse
    {
        $list = ContactList::forTeam($currentTeam->id)->findOrFail($list->id);
        $contact = Contact::forTeam($currentTeam->id)->findOrFail($contact->id);

        Gate::authorize('update', $list);

        $detachContact->handle($list, $contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed from list.')]);

        return back();
    }

    /**
     * Map a contact to the summary payload used by the list page.
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

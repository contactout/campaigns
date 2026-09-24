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

class ContactListController extends Controller
{
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

        return to_route('contacts.index');
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

        return back();
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

        return back();
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
}

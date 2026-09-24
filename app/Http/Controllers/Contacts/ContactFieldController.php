<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\CreateContactField;
use App\Actions\Contacts\DeleteContactField;
use App\Actions\Contacts\UpdateContactField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactFieldRequest;
use App\Http\Requests\Contacts\UpdateContactFieldRequest;
use App\Models\ContactField;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ContactFieldController extends Controller
{
    /**
     * Store a newly created custom contact field.
     */
    public function store(StoreContactFieldRequest $request, Team $currentTeam, CreateContactField $createContactField): RedirectResponse
    {
        Gate::authorize('create', [ContactField::class, $currentTeam]);

        /** @var array{name: string, fallback?: string|null, type: string} $data */
        $data = $request->validated();

        $createContactField->handle($currentTeam, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field created.')]);

        return back();
    }

    /**
     * Update the given custom contact field.
     */
    public function update(UpdateContactFieldRequest $request, Team $currentTeam, ContactField $field, UpdateContactField $updateContactField): RedirectResponse
    {
        $field = ContactField::forTeam($currentTeam->id)->findOrFail($field->id);

        Gate::authorize('update', $field);

        /** @var array{name: string, fallback?: string|null, type: string} $data */
        $data = $request->validated();

        $updateContactField->handle($field, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field updated.')]);

        return back();
    }

    /**
     * Delete the given custom contact field.
     */
    public function destroy(Team $currentTeam, ContactField $field, DeleteContactField $deleteContactField): RedirectResponse
    {
        $field = ContactField::forTeam($currentTeam->id)->findOrFail($field->id);

        Gate::authorize('delete', $field);

        $deleteContactField->handle($field);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field deleted.')]);

        return back();
    }
}

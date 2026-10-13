<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\StartContactImport;
use App\Enums\ContactImportDuplicateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactImportRequest;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ContactImportController extends Controller
{
    /**
     * Upload a CSV file and queue its contacts for import.
     */
    public function store(StoreContactImportRequest $request, Team $currentTeam, StartContactImport $startContactImport): RedirectResponse
    {
        Gate::authorize('create', [Contact::class, $currentTeam]);

        /** @var array<int|string, string|null> $columns */
        $columns = $request->validated('columns');
        $listId = $request->validated('list_id');

        $startContactImport->handle(
            $currentTeam,
            $request->user(),
            $request->file('file'),
            $columns,
            ContactImportDuplicateAction::from($request->validated('duplicate_action')),
            $listId === null ? null : (int) $listId,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import started.')]);

        return to_route('contacts.index');
    }
}

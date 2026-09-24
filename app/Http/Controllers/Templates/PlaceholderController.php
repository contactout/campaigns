<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\CreatePlaceholder;
use App\Actions\Templates\DeletePlaceholder;
use App\Actions\Templates\UpdatePlaceholder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StorePlaceholderRequest;
use App\Http\Requests\Templates\UpdatePlaceholderRequest;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PlaceholderController extends Controller
{
    /**
     * Store a newly created placeholder on the given email template.
     */
    public function store(StorePlaceholderRequest $request, Team $currentTeam, EmailTemplate $template, CreatePlaceholder $createPlaceholder): RedirectResponse
    {
        $template = EmailTemplate::forTeam($currentTeam->id)->findOrFail($template->id);

        Gate::authorize('update', $template);

        /** @var array{name: string, fallback?: string|null, type: string} $data */
        $data = $request->validated();

        $createPlaceholder->handle($template, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Placeholder created.')]);

        return back();
    }

    /**
     * Update the given placeholder.
     */
    public function update(UpdatePlaceholderRequest $request, Team $currentTeam, Placeholder $placeholder, UpdatePlaceholder $updatePlaceholder): RedirectResponse
    {
        $placeholder = Placeholder::forTeam($currentTeam->id)->findOrFail($placeholder->id);

        Gate::authorize('update', $placeholder);

        /** @var array{name: string, fallback?: string|null, type: string} $data */
        $data = $request->validated();

        $updatePlaceholder->handle($placeholder, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Placeholder updated.')]);

        return back();
    }

    /**
     * Delete the given placeholder.
     */
    public function destroy(Team $currentTeam, Placeholder $placeholder, DeletePlaceholder $deletePlaceholder): RedirectResponse
    {
        $placeholder = Placeholder::forTeam($currentTeam->id)->findOrFail($placeholder->id);

        Gate::authorize('delete', $placeholder);

        $deletePlaceholder->handle($placeholder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Placeholder deleted.')]);

        return back();
    }
}

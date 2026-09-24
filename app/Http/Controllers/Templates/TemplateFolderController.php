<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\CreateTemplateFolder;
use App\Actions\Templates\DeleteTemplateFolder;
use App\Actions\Templates\UpdateTemplateFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StoreTemplateFolderRequest;
use App\Http\Requests\Templates\UpdateTemplateFolderRequest;
use App\Models\Team;
use App\Models\TemplateFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TemplateFolderController extends Controller
{
    /**
     * Store a newly created template folder.
     */
    public function store(StoreTemplateFolderRequest $request, Team $currentTeam, CreateTemplateFolder $createTemplateFolder): RedirectResponse
    {
        Gate::authorize('create', [TemplateFolder::class, $currentTeam]);

        $createTemplateFolder->handle($currentTeam, $request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return back();
    }

    /**
     * Update the given template folder.
     */
    public function update(UpdateTemplateFolderRequest $request, Team $currentTeam, TemplateFolder $folder, UpdateTemplateFolder $updateTemplateFolder): RedirectResponse
    {
        $folder = TemplateFolder::forTeam($currentTeam->id)->findOrFail($folder->id);

        Gate::authorize('update', $folder);

        $updateTemplateFolder->handle($folder, $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder updated.')]);

        return back();
    }

    /**
     * Delete the given template folder.
     */
    public function destroy(Team $currentTeam, TemplateFolder $folder, DeleteTemplateFolder $deleteTemplateFolder): RedirectResponse
    {
        $folder = TemplateFolder::forTeam($currentTeam->id)->findOrFail($folder->id);

        Gate::authorize('delete', $folder);

        $deleteTemplateFolder->handle($folder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder deleted.')]);

        return back();
    }
}

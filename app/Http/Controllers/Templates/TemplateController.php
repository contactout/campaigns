<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\CreateTemplate;
use App\Actions\Templates\DeleteTemplate;
use App\Actions\Templates\UpdateTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use App\Models\TemplateFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * Display a listing of the team's email templates.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', [EmailTemplate::class, $currentTeam]);

        return Inertia::render('templates/index', [
            'templates' => EmailTemplate::query()
                ->forTeam($currentTeam->id)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (EmailTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'folder_id' => $template->folder_id,
                    'is_draft' => $template->is_draft,
                    'updated_at' => $template->updated_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'folders' => TemplateFolder::query()
                ->forTeam($currentTeam->id)
                ->withCount('templates')
                ->orderBy('name')
                ->get()
                ->map(fn (TemplateFolder $folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'templates_count' => (int) $folder->getAttribute('templates_count'),
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => $user->can('create', [EmailTemplate::class, $currentTeam]),
            ],
        ]);
    }

    /**
     * Store a newly created email template.
     */
    public function store(StoreTemplateRequest $request, Team $currentTeam, CreateTemplate $createTemplate): RedirectResponse
    {
        Gate::authorize('create', [EmailTemplate::class, $currentTeam]);

        /** @var array{name: string, subject?: string|null, body: string, folder_id?: int|null, is_draft?: bool} $data */
        $data = $request->validated();

        $template = $createTemplate->handle($currentTeam, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return to_route('templates.show', ['template' => $template]);
    }

    /**
     * Display the given email template.
     */
    public function show(Request $request, Team $currentTeam, EmailTemplate $template): Response
    {
        $template = EmailTemplate::forTeam($currentTeam->id)->findOrFail($template->id);

        Gate::authorize('view', $template);

        $template->load('placeholders');

        return Inertia::render('templates/show', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'body' => $template->body,
                'folder_id' => $template->folder_id,
                'is_draft' => $template->is_draft,
            ],
            'placeholders' => $template->placeholders
                ->map(fn (Placeholder $placeholder): array => [
                    'id' => $placeholder->id,
                    'name' => $placeholder->name,
                    'fallback' => $placeholder->fallback,
                    'type' => $placeholder->type->value,
                    'type_label' => $placeholder->type->label(),
                ])
                ->values()
                ->all(),
            'folders' => TemplateFolder::query()
                ->forTeam($currentTeam->id)
                ->orderBy('name')
                ->get()
                ->map(fn (TemplateFolder $folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                ])
                ->values()
                ->all(),
            'can' => [
                'update' => $request->user()->can('update', $template),
                'delete' => $request->user()->can('delete', $template),
            ],
        ]);
    }

    /**
     * Update the given email template.
     */
    public function update(UpdateTemplateRequest $request, Team $currentTeam, EmailTemplate $template, UpdateTemplate $updateTemplate): RedirectResponse
    {
        $template = EmailTemplate::forTeam($currentTeam->id)->findOrFail($template->id);

        Gate::authorize('update', $template);

        /** @var array{name: string, subject?: string|null, body: string, folder_id?: int|null, is_draft?: bool} $data */
        $data = $request->validated();

        $updateTemplate->handle($template, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return to_route('templates.show', ['template' => $template]);
    }

    /**
     * Delete the given email template.
     */
    public function destroy(Team $currentTeam, EmailTemplate $template, DeleteTemplate $deleteTemplate): RedirectResponse
    {
        $template = EmailTemplate::forTeam($currentTeam->id)->findOrFail($template->id);

        Gate::authorize('delete', $template);

        $deleteTemplate->handle($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return to_route('templates.index');
    }
}

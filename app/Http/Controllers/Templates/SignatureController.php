<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\CreateSignature;
use App\Actions\Templates\DeleteSignature;
use App\Actions\Templates\UpdateSignature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StoreSignatureRequest;
use App\Http\Requests\Templates\UpdateSignatureRequest;
use App\Models\Signature;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SignatureController extends Controller
{
    /**
     * Display a listing of the team's signatures.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', [Signature::class, $currentTeam]);

        return Inertia::render('signatures/index', [
            'signatures' => Signature::query()
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
                ->all(),
            'can' => [
                'create' => $user->can('create', [Signature::class, $currentTeam]),
            ],
        ]);
    }

    /**
     * Store a newly created signature.
     */
    public function store(StoreSignatureRequest $request, Team $currentTeam, CreateSignature $createSignature): RedirectResponse
    {
        Gate::authorize('create', [Signature::class, $currentTeam]);

        /** @var array{name: string, body: string, is_default?: bool} $data */
        $data = $request->validated();

        $createSignature->handle($currentTeam, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signature created.')]);

        return back();
    }

    /**
     * Update the given signature.
     */
    public function update(UpdateSignatureRequest $request, Team $currentTeam, Signature $signature, UpdateSignature $updateSignature): RedirectResponse
    {
        $signature = Signature::forTeam($currentTeam->id)->findOrFail($signature->id);

        Gate::authorize('update', $signature);

        /** @var array{name: string, body: string, is_default?: bool} $data */
        $data = $request->validated();

        $updateSignature->handle($signature, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signature updated.')]);

        return back();
    }

    /**
     * Delete the given signature.
     */
    public function destroy(Team $currentTeam, Signature $signature, DeleteSignature $deleteSignature): RedirectResponse
    {
        $signature = Signature::forTeam($currentTeam->id)->findOrFail($signature->id);

        Gate::authorize('delete', $signature);

        $deleteSignature->handle($signature);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signature deleted.')]);

        return back();
    }
}

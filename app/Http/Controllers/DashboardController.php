<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MailerConnection;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the team dashboard, including setup progress when a team is selected.
     */
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        $setup = $team === null ? null : [
            'has_connection' => MailerConnection::query()->forTeam($team->id)->exists(),
            'has_contact' => Contact::query()->forTeam($team->id)->exists(),
            'has_campaign' => Campaign::query()->forTeam($team->id)->exists(),
        ];

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'setup' => $setup,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Enums\RecipientStatus;
use App\Models\Recipient;
use App\Models\Unsubscribe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the signed unsubscribe confirmation flow for a campaign recipient.
 */
class UnsubscribeController extends Controller
{
    /**
     * Show the unsubscribe confirmation page for the recipient.
     */
    public function show(Recipient $recipient): Response
    {
        $recipient->loadMissing('contact.emailIdentity');

        return Inertia::render('unsubscribe', [
            'email' => $recipient->contact->email(),
            'action' => URL::signedRoute('unsubscribe.store', ['recipient' => $recipient]),
        ]);
    }

    /**
     * Record the unsubscribe and stop contacting the recipient.
     */
    public function store(Request $request, Recipient $recipient): RedirectResponse
    {
        $recipient->loadMissing(['campaign', 'contact.emailIdentity']);

        $email = (string) ($recipient->contact->email() ?? '');

        Unsubscribe::firstOrCreate(
            [
                'team_id' => $recipient->campaign->team_id,
                'email' => $email,
            ],
            [
                'campaign_id' => $recipient->campaign_id,
                'recipient_id' => $recipient->id,
                'reason' => 'recipient',
            ],
        );

        $recipient->status = RecipientStatus::Unsubscribed;
        $recipient->save();

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Unsubscribed;
        $contact->do_not_contact_at = Carbon::now();
        $contact->save();

        return to_route('unsubscribe.done');
    }
}

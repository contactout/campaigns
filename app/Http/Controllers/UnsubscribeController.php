<?php

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Enums\RecipientStatus;
use App\Models\Recipient;
use App\Services\Mail\SuppressionList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
    public function store(Request $request, Recipient $recipient, SuppressionList $suppressionList): RedirectResponse|HttpResponse
    {
        $recipient->loadMissing(['campaign', 'contact.emailIdentity']);

        $suppressionList->add(
            $recipient->campaign->team_id,
            (string) ($recipient->contact->email() ?? ''),
            'recipient',
            $recipient->campaign_id,
            $recipient->id,
        );

        $recipient->status = RecipientStatus::Unsubscribed;
        $recipient->save();

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Unsubscribed;
        $contact->do_not_contact_at = Carbon::now();
        $contact->save();

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return new HttpResponse('', 200);
        }

        return to_route('unsubscribe.done');
    }
}

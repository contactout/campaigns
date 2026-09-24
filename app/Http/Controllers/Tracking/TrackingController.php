<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Models\LinkClick;
use App\Models\TrackedLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public, unauthenticated tracking endpoints embedded in campaign emails.
 */
class TrackingController extends Controller
{
    /**
     * Record an email open and return a 1x1 transparent GIF.
     *
     * Repeats are tolerated and recorded as additional open events.
     */
    public function open(Request $request, CampaignEmail $campaignEmail): Response
    {
        EmailOpen::create([
            'campaign_email_id' => $campaignEmail->id,
            'recipient_id' => $campaignEmail->recipient_id,
            'opened_at' => now(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($campaignEmail->opened_at === null) {
            $campaignEmail->opened_at = now();
            $campaignEmail->save();
        }

        return new Response($this->transparentGif(), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Record a link click and redirect to the original destination.
     */
    public function click(Request $request, TrackedLink $hash): RedirectResponse
    {
        LinkClick::create([
            'tracked_link_id' => $hash->id,
            'campaign_email_id' => $hash->campaign_email_id,
            'recipient_id' => $hash->campaignEmail->recipient_id,
            'clicked_at' => now(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->away($hash->url);
    }

    /**
     * Get the bytes of a 1x1 transparent GIF.
     */
    private function transparentGif(): string
    {
        return (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
    }
}

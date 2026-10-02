<?php

namespace App\Http\Controllers\Tracking;

use App\Actions\Tracking\RecordEmailOpen;
use App\Http\Controllers\Controller;
use App\Models\CampaignEmail;
use App\Models\LinkClick;
use App\Models\TrackedLink;
use App\Support\TrackingUserAgent;
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
     * Repeats are tolerated and recorded as additional open events. Proxy
     * fetches are ignored, but the pixel is still served so the mail client
     * has nothing to report.
     */
    public function open(Request $request, CampaignEmail $campaignEmail, RecordEmailOpen $recordEmailOpen): Response
    {
        $recordEmailOpen->handle($campaignEmail, $request->userAgent());

        return new Response($this->transparentGif(), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Record a link click and redirect to the original destination.
     */
    public function click(Request $request, TrackedLink $hash, RecordEmailOpen $recordEmailOpen): RedirectResponse
    {
        LinkClick::create([
            'tracked_link_id' => $hash->id,
            'campaign_email_id' => $hash->campaign_email_id,
            'recipient_id' => $hash->campaignEmail->recipient_id,
            'clicked_at' => now(),
            'user_agent' => TrackingUserAgent::normalize($request->userAgent()),
        ]);

        // A click can indicate an open even when the client blocked the pixel
        // (link scanners follow URLs too), so infer a single open from it.
        $recordEmailOpen->handle($hash->campaignEmail, $request->userAgent(), multiple: false);

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

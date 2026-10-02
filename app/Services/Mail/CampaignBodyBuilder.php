<?php

namespace App\Services\Mail;

use App\Data\CampaignSettings;
use App\Models\CampaignEmail;
use App\Models\TrackedLink;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Prepares rendered campaign HTML for delivery.
 *
 * Every absolute http(s) anchor is rewritten to a tracked redirect URL backed by
 * a {@see TrackedLink} row, an unsubscribe link is added, and a 1x1 open-tracking
 * pixel is appended before the closing body tag (or at the end of the document
 * when there is none). Link rewriting and the pixel are each skipped when the
 * campaign has turned that kind of tracking off.
 */
class CampaignBodyBuilder
{
    /**
     * Rewrite the email's links and inject the unsubscribe link and pixel.
     */
    public function build(CampaignEmail $email, string $html): string
    {
        $settings = CampaignSettings::fromArray($email->campaign->settings);

        if ($settings->linkTracking) {
            $html = $this->rewriteLinks($email, $html);
        }

        // Appended after link rewriting, so the unsubscribe link is not wrapped
        // in click tracking. Mail merge orders its pipeline the same way.
        $html = $this->appendUnsubscribe($email, $html);

        if ($settings->openTracking) {
            $html = $this->appendPixel($email, $html);
        }

        return $html;
    }

    /**
     * Rewrite every absolute http(s) anchor href to a tracked redirect URL.
     */
    private function rewriteLinks(CampaignEmail $email, string $html): string
    {
        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\bhref=)(["\'])(https?:\/\/[^"\']*)\2/i',
            function (array $matches) use ($email): string {
                $url = $this->trackedUrl($email, $matches[3]);

                return $matches[1].$matches[2].$url.$matches[2];
            },
            $html,
        );
    }

    /**
     * Resolve, or create, the tracked link for the email and URL.
     */
    private function trackedUrl(CampaignEmail $email, string $url): string
    {
        $link = TrackedLink::query()
            ->where('campaign_email_id', $email->id)
            ->where('url', $url)
            ->first();

        if (! $link instanceof TrackedLink) {
            $link = TrackedLink::create([
                'campaign_email_id' => $email->id,
                'url' => $url,
                'hash' => $this->uniqueHash(),
            ]);
        }

        return route('tracking.click', ['hash' => $link->hash]);
    }

    /**
     * Generate a hash that is not yet used by another tracked link.
     */
    private function uniqueHash(): string
    {
        do {
            $hash = Str::random(32);
        } while (TrackedLink::query()->where('hash', $hash)->exists());

        return $hash;
    }

    /**
     * Append the unsubscribe link to the document.
     *
     * Points at the signed confirmation page rather than unsubscribing on a GET,
     * so a mail client prefetching the link cannot unsubscribe the recipient.
     */
    private function appendUnsubscribe(CampaignEmail $email, string $html): string
    {
        $url = URL::signedRoute('unsubscribe.show', ['recipient' => $email->recipient_id]);

        $paragraph = sprintf(
            '<p style="margin:16px 0;font-family:arial,sans-serif;font-size:10pt;color:#666">'
            .'No longer want these emails? <a href="%s">Unsubscribe</a>.</p>',
            e($url),
        );

        return $this->insertBeforeBodyEnd($html, $paragraph);
    }

    /**
     * Append the 1x1 open-tracking pixel to the document.
     */
    private function appendPixel(CampaignEmail $email, string $html): string
    {
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" alt="" style="display:none" />',
            route('tracking.open', ['campaignEmail' => $email->tracker]),
        );

        return $this->insertBeforeBodyEnd($html, $pixel);
    }

    /**
     * Insert markup at the end of the document body.
     */
    private function insertBeforeBodyEnd(string $html, string $markup): string
    {
        if (stripos($html, '</body>') === false) {
            return $html.$markup;
        }

        return (string) preg_replace('/<\/body>/i', $markup.'</body>', $html, 1);
    }
}

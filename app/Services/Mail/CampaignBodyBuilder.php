<?php

namespace App\Services\Mail;

use App\Models\CampaignEmail;
use App\Models\TrackedLink;
use Illuminate\Support\Str;

/**
 * Prepares rendered campaign HTML for delivery.
 *
 * Every absolute http(s) anchor is rewritten to a tracked redirect URL backed by
 * a {@see TrackedLink} row, and a 1x1 open-tracking pixel is appended before the
 * closing body tag (or at the end of the document when there is none).
 */
class CampaignBodyBuilder
{
    /**
     * Rewrite the email's links and inject the open-tracking pixel.
     */
    public function build(CampaignEmail $email, string $html): string
    {
        return $this->appendPixel($email, $this->rewriteLinks($email, $html));
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
     * Append the 1x1 open-tracking pixel to the document.
     */
    private function appendPixel(CampaignEmail $email, string $html): string
    {
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" alt="" style="display:none" />',
            route('tracking.open', ['campaignEmail' => $email->id]),
        );

        if (stripos($html, '</body>') === false) {
            return $html.$pixel;
        }

        return (string) preg_replace('/<\/body>/i', $pixel.'</body>', $html, 1);
    }
}

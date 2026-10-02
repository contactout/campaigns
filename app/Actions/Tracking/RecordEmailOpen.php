<?php

namespace App\Actions\Tracking;

use App\Enums\EmailStatus;
use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Support\TrackingUserAgent;

/**
 * Records an email open for a campaign email.
 *
 * Mail clients and their image proxies fetch the tracking pixel whether or not
 * a human reads the message, so the proxy check here is a heuristic: a request
 * is assumed to be a proxy fetch from its user agent, never proven.
 */
class RecordEmailOpen
{
    /**
     * User agents that fetch the pixel on behalf of a mailbox provider.
     *
     * These are the signatures mail merge suppresses; they are matched as
     * substrings, case-insensitively.
     */
    private const array PROXY_USER_AGENT_SIGNATURES = [
        'GoogleImageProxy',
        'YahooMailProxy',
        'SuperhumanProxy',
        'HeadlessChrome',
    ];

    /**
     * Several proxies send a bare "Mozilla/5.0" instead of a full user agent.
     */
    private const string BARE_USER_AGENT = 'Mozilla/5.0';

    /**
     * The statuses an email must have reached before an open counts.
     *
     * A pixel fetch for a scheduled, failed, or bounced email is not a read.
     */
    private const array OPENABLE_STATUSES = [
        EmailStatus::Sent,
        EmailStatus::Delivered,
        EmailStatus::Opened,
        EmailStatus::Replied,
    ];

    /**
     * Record an open for the email unless the request looks like a proxy fetch.
     *
     * @param  bool  $multiple  Record every open when true, only the first when false.
     */
    public function handle(CampaignEmail $email, ?string $userAgent, bool $multiple = true): void
    {
        if ($this->isProxyFetch($userAgent) || ! $this->canBeOpened($email)) {
            return;
        }

        $attributes = [
            'campaign_email_id' => $email->id,
            'recipient_id' => $email->recipient_id,
        ];

        $values = [
            'opened_at' => now(),
            'user_agent' => TrackingUserAgent::normalize($userAgent),
        ];

        if ($multiple) {
            EmailOpen::create($attributes + $values);
        } else {
            EmailOpen::firstOrCreate($attributes, $values);
        }

        if ($email->opened_at === null) {
            $email->opened_at = now();
            $email->save();
        }
    }

    /**
     * Determine whether the user agent belongs to a known proxy.
     *
     * The list mirrors the signatures mail merge suppresses, plus a bare
     * "Mozilla/5.0", which several proxies send instead of a full user agent.
     */
    private function isProxyFetch(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return false;
        }

        if ($userAgent === self::BARE_USER_AGENT) {
            return true;
        }

        foreach (self::PROXY_USER_AGENT_SIGNATURES as $signature) {
            if (stripos($userAgent, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the email is far enough along to have been read.
     */
    private function canBeOpened(CampaignEmail $email): bool
    {
        return in_array($email->status, self::OPENABLE_STATUSES, true);
    }
}

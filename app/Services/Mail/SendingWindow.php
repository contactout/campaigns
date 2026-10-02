<?php

namespace App\Services\Mail;

use App\Data\CampaignSettings;
use Carbon\CarbonImmutable;

/**
 * Moves a computed send time onto the campaign's allowed sending window.
 *
 * A campaign can restrict sending to certain weekdays and to an end-exclusive
 * hour range within the day. The window is evaluated in the recipient's
 * effective timezone, so "09:00 on a weekday" means 09:00 for the person
 * receiving the email rather than for the server.
 */
class SendingWindow
{
    /**
     * How many days the search will walk before giving up.
     *
     * A week is enough to reach any allowed weekday; the extra day absorbs a
     * window whose opening hour does not exist on a DST transition day.
     */
    private const int MAX_DAYS_AHEAD = 8;

    /**
     * Push the scheduled time forward to the next instant the campaign may send.
     */
    public function nextAllowedAt(CarbonImmutable $scheduled, string $timezone, CampaignSettings $settings): CarbonImmutable
    {
        if ($settings->isUnrestrictedByHour() && $settings->sendingDays === CampaignSettings::ALL_DAYS) {
            return $scheduled;
        }

        $local = $scheduled->setTimezone($timezone);

        for ($day = 0; $day < self::MAX_DAYS_AHEAD; $day++) {
            if (! $settings->allowsWeekday($local->isoWeekday())) {
                $local = $this->nextDayOpening($local, $settings);

                continue;
            }

            if ($settings->isUnrestrictedByHour()) {
                return $local->utc();
            }

            // Before the window opens, wait for it rather than skipping a day.
            if ($local->hour < $settings->sendingHourFrom) {
                $local = $local->setTime($settings->sendingHourFrom, 0);
            }

            // Re-check after setTime: PHP normalises an opening hour that does
            // not exist on a DST transition day, which can land outside the
            // window and needs another day.
            if ($settings->allowsHour($local->hour)) {
                return $local->utc();
            }

            $local = $this->nextDayOpening($local, $settings);
        }

        return $local->utc();
    }

    /**
     * Move to the opening of the next day, keeping the time when no hour window applies.
     */
    private function nextDayOpening(CarbonImmutable $local, CampaignSettings $settings): CarbonImmutable
    {
        $next = $local->addDay();

        return $settings->isUnrestrictedByHour()
            ? $next
            : $next->setTime($settings->sendingHourFrom, 0);
    }
}

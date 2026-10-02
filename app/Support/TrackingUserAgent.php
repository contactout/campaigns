<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalises the user agent stored against a tracking event.
 *
 * The `email_opens` and `link_clicks` columns are `varchar(255)`, and mail
 * clients routinely send user agents longer than that, which would otherwise
 * fail the insert.
 */
class TrackingUserAgent
{
    /**
     * The maximum number of characters the tracking columns accept.
     */
    public const int MAX_LENGTH = 255;

    /**
     * Appended to a user agent that had to be cut short.
     */
    private const string TRUNCATION_MARKER = '...';

    /**
     * Trim the user agent and cut it to the length the column accepts.
     *
     * The marker is counted inside the limit: `Str::limit` appends it after
     * truncating, so asking for the full column width would overflow it.
     */
    public static function normalize(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        return Str::limit(
            $userAgent,
            self::MAX_LENGTH - strlen(self::TRUNCATION_MARKER),
            self::TRUNCATION_MARKER,
        );
    }
}

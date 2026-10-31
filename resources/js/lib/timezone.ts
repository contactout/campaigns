/**
 * Format an IANA timezone the way the campaign board does:
 * `(UTC+08:00) Kuala Lumpur`.
 *
 * The offset is resolved for the current instant, so a zone with daylight
 * saving shows the offset in force today rather than a fixed one.
 */
export function timezoneLabel(timezone: string): string {
    const city = timezone.split('/').pop()?.replaceAll('_', ' ') ?? timezone;

    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            timeZoneName: 'longOffset',
        }).formatToParts(new Date());

        const offset =
            parts.find((part) => part.type === 'timeZoneName')?.value ?? '';

        if (offset === '' || offset === 'GMT') {
            return `(UTC+00:00) ${city}`;
        }

        return `(${offset.replace('GMT', 'UTC')}) ${city}`;
    } catch {
        return city;
    }
}

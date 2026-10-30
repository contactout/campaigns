/**
 * Short relative timestamp for table sublines: "2h ago", "yesterday",
 * "Oct 10", or "Oct 10, 2024" once the date leaves the current year.
 */
export function formatRelativeTime(
    value: string | null | undefined,
    now: Date = new Date(),
): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const minutes = Math.floor((now.getTime() - date.getTime()) / 60_000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);

    if (days === 1) {
        return 'yesterday';
    }

    if (days < 7) {
        return `${days}d ago`;
    }

    const sameYear = date.getFullYear() === now.getFullYear();

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        ...(sameYear ? {} : { year: 'numeric' }),
    });
}

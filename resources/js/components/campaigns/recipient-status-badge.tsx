import { Badge } from '@/components/ui/badge';
import type { RecipientDisplayStatus, RecipientSummary } from '@/types';

const variants: Record<
    RecipientDisplayStatus,
    'success' | 'warning' | 'info' | 'destructive' | 'neutral'
> = {
    replied: 'success',
    bounced: 'destructive',
    unsubscribed: 'neutral',
    opened: 'warning',
    contacted: 'info',
    not_contacted: 'neutral',
    error: 'destructive',
    paused: 'neutral',
    archived: 'neutral',
    completed: 'neutral',
};

export default function RecipientStatusBadge({
    recipient,
}: {
    recipient: RecipientSummary;
}) {
    return (
        <Badge
            variant={variants[recipient.display_status] ?? 'neutral'}
            data-test="campaign-recipient-status"
        >
            {recipient.display_status_label}
        </Badge>
    );
}

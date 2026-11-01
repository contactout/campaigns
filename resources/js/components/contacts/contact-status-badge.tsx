import { Badge } from '@/components/ui/badge';
import type { ContactStatus } from '@/types';

const variants: Record<
    ContactStatus,
    'success' | 'warning' | 'info' | 'neutral' | 'destructive'
> = {
    NotContacted: 'neutral',
    Contacted: 'info',
    Replied: 'success',
    Bounced: 'destructive',
    Unsubscribed: 'neutral',
    DoNotContact: 'warning',
};

/**
 * The board's copy for each enum value. The stored value stays `NotContacted`
 * and `DoNotContact`; board 06 A labels them `New` and `Do not contact`.
 */
const labels: Record<ContactStatus, string> = {
    NotContacted: 'New',
    Contacted: 'Contacted',
    Replied: 'Replied',
    Bounced: 'Bounced',
    Unsubscribed: 'Unsubscribed',
    DoNotContact: 'Do not contact',
};

export function contactStatusLabel(status: string, fallback = ''): string {
    return labels[status as ContactStatus] ?? (fallback || status);
}

type Props = {
    status: string;
    label: string;
};

export default function ContactStatusBadge({ status, label }: Props) {
    return (
        <Badge variant={variants[status as ContactStatus] ?? 'neutral'}>
            {contactStatusLabel(status, label)}
        </Badge>
    );
}

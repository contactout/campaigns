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

type Props = {
    status: string;
    label: string;
};

export default function ContactStatusBadge({ status, label }: Props) {
    return (
        <Badge variant={variants[status as ContactStatus] ?? 'neutral'}>
            {label}
        </Badge>
    );
}

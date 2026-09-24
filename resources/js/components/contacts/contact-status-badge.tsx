import { Badge } from '@/components/ui/badge';
import type { ContactStatus } from '@/types';

const variants: Record<
    ContactStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    NotContacted: 'outline',
    Contacted: 'secondary',
    Replied: 'default',
    Bounced: 'destructive',
    Unsubscribed: 'destructive',
    DoNotContact: 'destructive',
};

type Props = {
    status: string;
    label: string;
};

export default function ContactStatusBadge({ status, label }: Props) {
    return (
        <Badge variant={variants[status as ContactStatus] ?? 'outline'}>
            {label}
        </Badge>
    );
}

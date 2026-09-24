import { Badge } from '@/components/ui/badge';
import type { MailerConnectionStatus } from '@/types';

const variants: Record<
    MailerConnectionStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    Pending: 'outline',
    Active: 'default',
    Deactivated: 'destructive',
    Disconnected: 'secondary',
};

type Props = {
    status: string;
    label: string;
};

export default function MailerConnectionStatusBadge({ status, label }: Props) {
    return (
        <Badge
            variant={variants[status as MailerConnectionStatus] ?? 'outline'}
        >
            {label}
        </Badge>
    );
}

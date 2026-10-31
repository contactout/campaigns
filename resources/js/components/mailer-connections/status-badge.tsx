import { Badge } from '@/components/ui/badge';
import type { MailerConnectionStatus } from '@/types';

const variants: Record<
    MailerConnectionStatus,
    'success' | 'warning' | 'neutral'
> = {
    Pending: 'warning',
    Active: 'success',
    Deactivated: 'neutral',
    Disconnected: 'neutral',
};

type Props = {
    status: string;
    label: string;
};

export default function MailerConnectionStatusBadge({ status, label }: Props) {
    return (
        <Badge
            variant={variants[status as MailerConnectionStatus] ?? 'neutral'}
        >
            {label}
        </Badge>
    );
}

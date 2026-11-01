import { Badge } from '@/components/ui/badge';
import type { MailerConnectionDisplayStatus } from '@/types';

const variants: Record<
    MailerConnectionDisplayStatus,
    'success' | 'warning' | 'neutral' | 'destructive'
> = {
    Verified: 'success',
    Failed: 'destructive',
    Pending: 'warning',
    Deactivated: 'neutral',
    Disconnected: 'neutral',
};

type Props = {
    status: MailerConnectionDisplayStatus;
    label: string;
};

/**
 * The connections table pill.
 *
 * `status` is the derived display status, not the stored enum: an Active
 * connection with a recorded exception is Failed here, which is exactly when
 * the dashboard counts it as needing attention.
 */
export default function MailerConnectionStatusBadge({ status, label }: Props) {
    return <Badge variant={variants[status] ?? 'neutral'}>{label}</Badge>;
}

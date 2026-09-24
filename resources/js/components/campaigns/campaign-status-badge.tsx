import { Badge } from '@/components/ui/badge';
import type { CampaignStatus } from '@/types';

const variants: Record<
    CampaignStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    Draft: 'outline',
    Active: 'default',
    Stopped: 'secondary',
    Completed: 'secondary',
    Archived: 'outline',
};

type Props = {
    status: string;
    label: string;
};

export default function CampaignStatusBadge({ status, label }: Props) {
    return (
        <Badge variant={variants[status as CampaignStatus] ?? 'outline'}>
            {label}
        </Badge>
    );
}

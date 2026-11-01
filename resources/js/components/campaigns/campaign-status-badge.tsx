import { Badge } from '@/components/ui/badge';
import type { CampaignStatus } from '@/types';

const variants: Record<CampaignStatus, 'success' | 'neutral'> = {
    Active: 'success',
    Draft: 'neutral',
    Stopped: 'neutral',
    Completed: 'neutral',
    Archived: 'neutral',
};

type Props = {
    status: string;
    label: string;
};

export default function CampaignStatusBadge({ status, label }: Props) {
    return (
        <Badge variant={variants[status as CampaignStatus] ?? 'neutral'}>
            {label}
        </Badge>
    );
}

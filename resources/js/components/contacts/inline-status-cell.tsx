import { router, usePage } from '@inertiajs/react';
import ContactStatusBadge from '@/components/contacts/contact-status-badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cell } from '@/routes/contacts';
import type { StatusOption } from '@/types';

type Props = {
    contactId: number;
    status: string;
    label: string;
    statuses: StatusOption[];
};

export default function InlineStatusCell({
    contactId,
    status,
    label,
    statuses,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const update = (value: string) => {
        if (value === status) {
            return;
        }

        router.patch(
            cell.url([slug, contactId]),
            { field: 'status', value },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Select value={status} onValueChange={update}>
            <SelectTrigger
                data-grid-cell
                aria-label={`Status: ${label}`}
                className="h-11 w-full justify-start rounded-none border-0 bg-transparent px-3 shadow-none focus:ring-2 focus:ring-primary focus:ring-inset"
            >
                <SelectValue>
                    <ContactStatusBadge status={status} label={label} />
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                {statuses.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

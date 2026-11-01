import { router, usePage } from '@inertiajs/react';
import ContactStatusBadge, {
    contactStatusLabel,
} from '@/components/contacts/contact-status-badge';
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
                size="sm"
                data-grid-cell
                aria-label={`Status: ${contactStatusLabel(status, label)}`}
                title={contactStatusLabel(status, label)}
                className="h-8 w-full justify-start overflow-hidden rounded-none border-0 bg-transparent px-3 shadow-none focus:ring-2 focus:ring-ring focus:ring-inset [&>svg]:hidden"
            >
                <SelectValue>
                    <ContactStatusBadge status={status} label={label} />
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                {statuses.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {contactStatusLabel(option.value, option.label)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

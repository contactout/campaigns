import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ContactStatusBadge from '@/components/contacts/contact-status-badge';
import CreateContactModal from '@/components/contacts/create-contact-modal';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/contacts';
import type {
    ContactSummary,
    ListOption,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    contacts: Paginated<ContactSummary>;
    filters: {
        q: string | null;
        list: number | null;
    };
    lists: ListOption[];
    statuses: StatusOption[];
    can: {
        create: boolean;
    };
};

export default function ContactsIndex({
    contacts,
    filters,
    lists,
    statuses,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const [search, setSearch] = useState(filters.q ?? '');

    const navigate = (params: { q?: string; list?: string }) => {
        router.get(
            index.url(currentTeam?.slug ?? ''),
            {
                q: params.q ?? filters.q ?? '',
                list: params.list ?? filters.list ?? '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Contacts" />

            <h1 className="sr-only">Contacts</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Contacts"
                        description="People you reach out to"
                    />

                    {can.create ? (
                        <CreateContactModal lists={lists} statuses={statuses}>
                            <Button data-test="contacts-new-button">
                                <Plus /> New contact
                            </Button>
                        </CreateContactModal>
                    ) : null}
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate({ q: search });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by name or email"
                        className="max-w-xs"
                    />

                    <Select
                        value={filters.list ? String(filters.list) : 'all'}
                        onValueChange={(value) =>
                            navigate({ list: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All lists" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All lists</SelectItem>
                            {lists.map((list) => (
                                <SelectItem
                                    key={list.id}
                                    value={String(list.id)}
                                >
                                    {list.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {contacts.data.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        No contacts yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Lists
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.data.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className="border-t"
                                        data-test="contact-row"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {contact.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {contact.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            <ContactStatusBadge
                                                status={contact.status}
                                                label={contact.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {contact.lists_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={show([
                                                        currentTeam?.slug ?? '',
                                                        contact.id,
                                                    ])}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={contacts.links} />
            </div>
        </>
    );
}

ContactsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Contacts',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

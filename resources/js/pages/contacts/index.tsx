import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CreateContactModal from '@/components/contacts/create-contact-modal';
import InlineCell from '@/components/contacts/inline-cell';
import InlineStatusCell from '@/components/contacts/inline-status-cell';
import ListTabs from '@/components/contacts/list-tabs';
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
    ContactListSummary,
    ContactSummary,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    contacts: Paginated<ContactSummary>;
    filters: {
        q: string | null;
        list: number | null;
        status: string | null;
    };
    lists: ContactListSummary[];
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
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.q ?? '');

    const listOptions = lists.map((list) => ({
        id: list.id,
        name: list.name,
    }));

    const navigate = (params: {
        q?: string;
        list?: number | null;
        status?: string;
    }) => {
        router.get(
            index.url(slug),
            {
                q: params.q ?? filters.q ?? '',
                list:
                    params.list !== undefined
                        ? (params.list ?? '')
                        : (filters.list ?? ''),
                status: params.status ?? filters.status ?? '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Contacts" />

            <h1 className="sr-only">Contacts</h1>

            <div className="flex flex-col space-y-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Contacts"
                        description="People you reach out to"
                    />

                    {can.create ? (
                        <CreateContactModal
                            lists={listOptions}
                            statuses={statuses}
                            defaultListIds={
                                filters.list ? [filters.list] : undefined
                            }
                        >
                            <Button data-test="contacts-new-button">
                                <Plus /> New contact
                            </Button>
                        </CreateContactModal>
                    ) : null}
                </div>

                <ListTabs
                    lists={lists}
                    activeListId={filters.list}
                    canCreate={can.create}
                />

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
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            navigate({ status: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
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
                        <table className="w-full border-collapse text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="border-b px-4 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="border-b px-4 py-2 font-medium">
                                        Email
                                    </th>
                                    <th className="border-b px-4 py-2 font-medium">
                                        Phone
                                    </th>
                                    <th className="border-b px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="border-b px-4 py-2 font-medium">
                                        Lists
                                    </th>
                                    <th className="border-b px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.data.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className="border-b hover:bg-muted/20"
                                        data-test="contact-row"
                                    >
                                        <td className="border-r px-2 py-1 font-medium">
                                            <InlineCell
                                                contactId={contact.id}
                                                field="name"
                                                value={contact.name}
                                                placeholder="Add name"
                                            />
                                        </td>
                                        <td className="border-r px-2 py-1 text-muted-foreground">
                                            <InlineCell
                                                contactId={contact.id}
                                                field="email"
                                                type="email"
                                                value={contact.email}
                                                placeholder="Add email"
                                            />
                                        </td>
                                        <td className="border-r px-2 py-1 text-muted-foreground">
                                            <InlineCell
                                                contactId={contact.id}
                                                field="phone"
                                                value={contact.phone}
                                                placeholder="Add phone"
                                            />
                                        </td>
                                        <td className="border-r px-2 py-1">
                                            <InlineStatusCell
                                                contactId={contact.id}
                                                status={contact.status}
                                                label={contact.status_label}
                                                statuses={statuses}
                                            />
                                        </td>
                                        <td className="border-r px-4 py-1 text-muted-foreground">
                                            {contact.lists_count}
                                        </td>
                                        <td className="px-2 py-1 text-right">
                                            <Link
                                                href={show([slug, contact.id])}
                                                className="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                                            >
                                                Open
                                            </Link>
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

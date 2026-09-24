import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus, Search } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
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
    const gridViewportRef = useRef<HTMLDivElement>(null);
    const [visibleRows, setVisibleRows] = useState(8);

    useEffect(() => {
        const viewport = gridViewportRef.current;

        if (!viewport) {
            return;
        }

        const updateRows = () => {
            setVisibleRows(
                Math.max(8, Math.ceil((viewport.clientHeight - 44) / 44)),
            );
        };

        updateRows();

        const observer = new ResizeObserver(updateRows);
        observer.observe(viewport);

        return () => observer.disconnect();
    }, []);

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

    const handleGridKeyDown = (event: KeyboardEvent<HTMLTableElement>) => {
        if (
            !(event.target instanceof HTMLElement) ||
            !event.target.matches('[data-grid-cell]')
        ) {
            return;
        }

        const column = Number(event.target.closest('td')?.dataset.gridColumn);
        const row = Number(event.target.closest('tr')?.dataset.gridRow);

        // Leave status dropdown arrows to the select component.
        if (column === 3) {
            return;
        }

        const nextColumn =
            column +
            (event.key === 'ArrowRight'
                ? 1
                : event.key === 'ArrowLeft'
                  ? -1
                  : 0);
        const nextRow =
            row +
            (event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0);

        if (
            !['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(
                event.key,
            )
        ) {
            return;
        }

        const next = event.currentTarget.querySelector<HTMLElement>(
            `tr[data-grid-row="${nextRow}"] td[data-grid-column="${nextColumn}"] [data-grid-cell]`,
        );

        if (next) {
            event.preventDefault();
            next.focus();
        }
    };

    return (
        <>
            <Head title="Contacts" />

            <h1 className="sr-only">Contacts</h1>

            <div className="flex h-[calc(100svh-4rem)] min-h-[28rem] w-full min-w-0 flex-col gap-5 px-4 py-6 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Contacts"
                        description={`${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'} in this view`}
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

                <form
                    role="search"
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate({ q: search });
                    }}
                >
                    <div className="relative min-w-48 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label="Search contacts"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or email"
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            navigate({ status: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter contacts by status"
                            className="w-44 sm:w-48"
                        >
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

                <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card">
                    <div
                        ref={gridViewportRef}
                        className="min-h-0 flex-1 overflow-auto overscroll-x-contain"
                    >
                        <table
                            onKeyDown={handleGridKeyDown}
                            className="w-full min-w-[1000px] table-fixed border-separate border-spacing-0 text-sm"
                            aria-label="Contacts spreadsheet"
                        >
                            <colgroup>
                                <col className="w-12" />
                                <col className="w-60" />
                                <col className="w-72" />
                                <col className="w-48" />
                                <col className="w-44" />
                                <col className="w-24" />
                                <col className="w-24" />
                            </colgroup>
                            <thead className="sticky top-0 z-20 bg-muted text-left text-muted-foreground">
                                <tr className="h-11">
                                    <th
                                        scope="col"
                                        className="sticky left-0 z-30 border-r border-b bg-muted text-center font-normal"
                                    >
                                        #
                                    </th>
                                    <th
                                        scope="col"
                                        className="sticky left-12 z-30 border-r border-b bg-muted px-3 font-medium"
                                    >
                                        Name
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-r border-b px-3 font-medium"
                                    >
                                        Email
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-r border-b px-3 font-medium"
                                    >
                                        Phone
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-r border-b px-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-r border-b px-3 font-medium"
                                    >
                                        Lists
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-b px-3 font-medium"
                                    >
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.data.map((contact, row) => (
                                    <tr
                                        key={contact.id}
                                        data-grid-row={row}
                                        data-test="contact-row"
                                        className="group h-11 hover:bg-muted/30"
                                    >
                                        <th
                                            scope="row"
                                            className="sticky left-0 z-10 border-r border-b bg-muted/60 text-center text-xs font-normal text-muted-foreground group-hover:bg-muted"
                                        >
                                            {(contacts.from ?? 1) + row}
                                        </th>
                                        <td
                                            data-grid-column={0}
                                            className="sticky left-12 z-10 border-r border-b bg-card font-medium group-hover:bg-muted/30"
                                        >
                                            <InlineCell
                                                contactId={contact.id}
                                                field="name"
                                                columnLabel="Name"
                                                value={contact.name}
                                                placeholder="Add name"
                                            />
                                        </td>
                                        <td
                                            data-grid-column={1}
                                            className="border-r border-b text-muted-foreground"
                                        >
                                            <InlineCell
                                                contactId={contact.id}
                                                field="email"
                                                columnLabel="Email"
                                                type="email"
                                                value={contact.email}
                                                placeholder="Add email"
                                            />
                                        </td>
                                        <td
                                            data-grid-column={2}
                                            className="border-r border-b text-muted-foreground"
                                        >
                                            <InlineCell
                                                contactId={contact.id}
                                                field="phone"
                                                columnLabel="Phone"
                                                value={contact.phone}
                                                placeholder="Add phone"
                                            />
                                        </td>
                                        <td
                                            data-grid-column={3}
                                            className="border-r border-b"
                                        >
                                            <InlineStatusCell
                                                contactId={contact.id}
                                                status={contact.status}
                                                label={contact.status_label}
                                                statuses={statuses}
                                            />
                                        </td>
                                        <td className="border-r border-b px-3 text-muted-foreground">
                                            {contact.lists_count}
                                        </td>
                                        <td className="border-b px-3">
                                            <Link
                                                href={show([slug, contact.id])}
                                                aria-label={`Open ${contact.name}`}
                                                className="inline-flex items-center gap-1 font-medium text-muted-foreground hover:text-foreground focus-visible:rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                Open{' '}
                                                <ArrowUpRight className="size-3.5" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {contacts.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="h-40 border-b text-center"
                                        >
                                            <p className="font-medium">
                                                {filters.q ||
                                                filters.status ||
                                                filters.list
                                                    ? 'No matching contacts'
                                                    : 'No contacts yet'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {filters.q ||
                                                filters.status ||
                                                filters.list
                                                    ? 'Try another search or change your filters.'
                                                    : 'Add your first contact to start filling this sheet.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                                {Array.from(
                                    {
                                        length: Math.max(
                                            0,
                                            visibleRows -
                                                contacts.data.length -
                                                (contacts.data.length === 0
                                                    ? 4
                                                    : 0),
                                        ),
                                    },
                                    (_, row) => (
                                        <tr
                                            key={`blank-${row}`}
                                            aria-hidden="true"
                                            className="h-11 text-muted-foreground/60"
                                        >
                                            <td className="sticky left-0 border-r border-b bg-muted/60 text-center text-xs">
                                                {(contacts.from ?? 1) +
                                                    contacts.data.length +
                                                    row}
                                            </td>
                                            <td className="sticky left-12 border-r border-b bg-card" />
                                            <td className="border-r border-b" />
                                            <td className="border-r border-b" />
                                            <td className="border-r border-b" />
                                            <td className="border-r border-b" />
                                            <td className="border-b" />
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-xs text-muted-foreground">
                        <span>
                            {contacts.total === 0
                                ? '0 contacts'
                                : `Showing ${contacts.from}–${contacts.to} of ${contacts.total} contacts`}
                        </span>
                        <span>
                            Click a cell to edit · Use arrow keys to move
                        </span>
                    </div>
                    <ListTabs
                        lists={lists}
                        activeListId={filters.list}
                        canCreate={can.create}
                    />
                </div>
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

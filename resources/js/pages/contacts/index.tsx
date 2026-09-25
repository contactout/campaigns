import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import ContactFieldFormModal from '@/components/contacts/contact-field-form-modal';
import CreateContactModal from '@/components/contacts/create-contact-modal';
import DeleteContactFieldModal from '@/components/contacts/delete-contact-field-modal';
import InlineCell from '@/components/contacts/inline-cell';
import InlinePropertyCell from '@/components/contacts/inline-property-cell';
import InlineStatusCell from '@/components/contacts/inline-status-cell';
import ListTabs from '@/components/contacts/list-tabs';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/contacts';
import type {
    ContactField,
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
    };
    fields: ContactField[];
    lists: ContactListSummary[];
    statuses: StatusOption[];
    can: {
        create: boolean;
    };
};

export default function ContactsIndex({
    contacts,
    filters,
    fields,
    lists,
    statuses,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.q ?? '');
    const gridViewportRef = useRef<HTMLDivElement>(null);
    const [visibleRows, setVisibleRows] = useState(8);
    const [fieldModalOpen, setFieldModalOpen] = useState(false);
    const [editingField, setEditingField] = useState<ContactField | null>(null);
    const [deletingField, setDeletingField] = useState<ContactField | null>(
        null,
    );

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

    const navigate = (params: { q?: string; list?: number | null }) => {
        router.get(
            index.url(slug),
            {
                q: params.q ?? filters.q ?? '',
                list:
                    params.list !== undefined
                        ? (params.list ?? '')
                        : (filters.list ?? ''),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openNewField = () => {
        setEditingField(null);
        setFieldModalOpen(true);
    };

    const openEditField = (field: ContactField) => {
        setEditingField(field);
        setFieldModalOpen(true);
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

        if (
            column === 3 ||
            !['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(
                event.key,
            )
        ) {
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

                    <div className="flex flex-wrap items-center gap-2">
                        {can.create ? (
                            <Button
                                variant="secondary"
                                onClick={openNewField}
                                data-test="contacts-add-column"
                            >
                                <Plus /> Add column
                            </Button>
                        ) : null}

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
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <div className="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card">
                    <div
                        ref={gridViewportRef}
                        className="min-h-0 flex-1 overflow-auto overscroll-x-contain"
                    >
                        <table
                            onKeyDown={handleGridKeyDown}
                            className="w-full table-fixed border-separate border-spacing-0 text-sm"
                            style={{ minWidth: 1136 + fields.length * 200 }}
                            aria-label="Contacts spreadsheet"
                        >
                            <colgroup>
                                <col className="w-12" />
                                <col className="w-60" />
                                <col className="w-72" />
                                <col className="w-48" />
                                <col className="w-44" />
                                <col className="w-24" />
                                {fields.map((field) => (
                                    <col key={field.id} className="w-[200px]" />
                                ))}
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
                                    {fields.map((field) => (
                                        <th
                                            key={field.id}
                                            scope="col"
                                            className="group border-r border-b px-3 font-medium"
                                        >
                                            <span className="flex min-w-0 items-center gap-1">
                                                <span
                                                    className="min-w-0 truncate"
                                                    title={field.name}
                                                >
                                                    {field.name}
                                                </span>
                                                {can.create && (
                                                    <span className="ml-auto flex shrink-0 items-center sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openEditField(
                                                                    field,
                                                                )
                                                            }
                                                            aria-label={`Edit ${field.name}`}
                                                            className="rounded p-1 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setDeletingField(
                                                                    field,
                                                                )
                                                            }
                                                            aria-label={`Delete ${field.name}`}
                                                            className="rounded p-1 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    </span>
                                                )}
                                            </span>
                                        </th>
                                    ))}
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
                                        {fields.map((field, column) => (
                                            <td
                                                key={field.id}
                                                data-grid-column={4 + column}
                                                className="border-r border-b text-muted-foreground"
                                            >
                                                <InlinePropertyCell
                                                    contactId={contact.id}
                                                    fieldId={field.id}
                                                    fieldName={field.name}
                                                    value={
                                                        contact.properties[
                                                            field.id
                                                        ] ?? null
                                                    }
                                                />
                                            </td>
                                        ))}
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
                                {Array.from(
                                    {
                                        length: Math.max(
                                            0,
                                            visibleRows - contacts.data.length,
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
                                            {fields.map((field) => (
                                                <td
                                                    key={field.id}
                                                    className="border-r border-b"
                                                />
                                            ))}
                                            <td className="border-b" />
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                    {contacts.data.length === 0 && (
                        <div className="pointer-events-none absolute inset-x-4 top-16 z-10 flex justify-center">
                            <div className="pointer-events-auto w-full max-w-md rounded-xl border bg-card p-6 text-center shadow-sm">
                                <h2 className="font-semibold">
                                    {filters.q || filters.list
                                        ? 'No matching contacts'
                                        : 'No contacts yet'}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {filters.q || filters.list
                                        ? 'Try another search or switch lists.'
                                        : 'Add your first contact to start filling this sheet.'}
                                </p>
                                {can.create && !filters.q && (
                                    <CreateContactModal
                                        lists={listOptions}
                                        statuses={statuses}
                                        defaultListIds={
                                            filters.list
                                                ? [filters.list]
                                                : undefined
                                        }
                                    >
                                        <Button className="mt-4">
                                            <Plus /> New contact
                                        </Button>
                                    </CreateContactModal>
                                )}
                            </div>
                        </div>
                    )}
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

            {fieldModalOpen ? (
                <ContactFieldFormModal
                    field={editingField}
                    open={fieldModalOpen}
                    onOpenChange={setFieldModalOpen}
                />
            ) : null}

            {deletingField ? (
                <DeleteContactFieldModal
                    field={deletingField}
                    open={deletingField !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeletingField(null);
                        }
                    }}
                />
            ) : null}
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

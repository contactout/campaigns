import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Filter,
    Plus,
    Search,
    Settings,
    Send,
    Trash2,
    Upload,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AddToCampaignModal from '@/components/contacts/add-to-campaign-modal';
import ContactFieldFormModal from '@/components/contacts/contact-field-form-modal';
import ContactGrid from '@/components/contacts/contact-grid';
import CreateContactModal from '@/components/contacts/create-contact-modal';
import DeleteContactFieldModal from '@/components/contacts/delete-contact-field-modal';
import ImportContactsModal from '@/components/contacts/import-contacts-modal';
import ImportSummary from '@/components/contacts/import-summary';
import ListTabs from '@/components/contacts/list-tabs';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import PageHead from '@/components/page-head';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cell, destroy, index as contactsIndex } from '@/routes/contacts';
import type {
    ContactCampaignOption,
    ContactField,
    ContactImportSummary,
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
    latestImport: ContactImportSummary | null;
    campaigns: ContactCampaignOption[];
    can: {
        create: boolean;
    };
};

const DELETE_COPY =
    'This removes the contacts from their lists and from this view. It cannot be undone here.';

export default function ContactsIndex({
    contacts,
    filters,
    fields,
    lists,
    statuses,
    latestImport,
    campaigns,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [busy, setBusy] = useState<'do-not-contact' | 'delete' | null>(null);
    const [bulkError, setBulkError] = useState<string | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [campaignOpen, setCampaignOpen] = useState(false);
    const [fieldModalOpen, setFieldModalOpen] = useState(false);
    const [editingField, setEditingField] = useState<ContactField | null>(null);
    const [deletingField, setDeletingField] = useState<ContactField | null>(
        null,
    );

    // The server owns `q`; keep the field in step when a visit reloads it.
    useEffect(() => {
        setSearch(filters.q ?? '');
    }, [filters.q]);

    // Selection never crosses a page or filter change.
    useEffect(() => {
        setSelected([]);
    }, [contacts.current_page, filters.q, filters.list]);

    const pageIds = contacts.data.map((contact) => contact.id);
    const selectedOnPage = selected.filter((id) => pageIds.includes(id));
    const selectedCount = selectedOnPage.length;
    const allAlreadyDoNotContact =
        selectedCount > 0 &&
        selectedOnPage.every(
            (id) =>
                contacts.data.find((contact) => contact.id === id)?.status ===
                'DoNotContact',
        );

    const listOptions = lists.map((list) => ({
        id: list.id,
        name: list.name,
    }));

    const hasFilters = Boolean(filters.q || filters.list);
    const isFirstRun = contacts.total === 0 && !hasFilters;
    const isOutOfRange =
        contacts.data.length === 0 && !hasFilters && contacts.current_page > 1;

    const previousLink = contacts.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = contacts.links.find((link) => link.label.includes('Next'));

    const navigate = (params: { q?: string; list?: number | null } = {}) => {
        router.get(
            contactsIndex.url(slug),
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

    const clearFilters = () => {
        setSearch('');
        router.get(
            contactsIndex.url(slug),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggle = (contactId: number) => {
        setSelected((current) =>
            current.includes(contactId)
                ? current.filter((id) => id !== contactId)
                : [...current, contactId],
        );
    };

    const toggleAll = (checked: boolean) => {
        setSelected(checked ? pageIds : []);
    };

    const openNewField = () => {
        setEditingField(null);
        setFieldModalOpen(true);
    };

    const openEditField = (field: ContactField) => {
        setEditingField(field);
        setFieldModalOpen(true);
    };

    const markDoNotContact = () => {
        const ids = selectedOnPage.filter(
            (id) =>
                contacts.data.find((contact) => contact.id === id)?.status !==
                'DoNotContact',
        );

        if (ids.length === 0) {
            setSelected([]);

            return;
        }

        setBusy('do-not-contact');
        setBulkError(null);

        let cursor = 0;

        const next = () => {
            if (cursor >= ids.length) {
                setBusy(null);
                setSelected([]);

                return;
            }

            const id = ids[cursor++];

            router.patch(
                cell.url([slug, id]),
                { field: 'status', value: 'DoNotContact' },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: next,
                    onError: () => {
                        setBusy(null);
                        setBulkError(
                            'Could not mark every selected contact as do not contact.',
                        );
                    },
                },
            );
        };

        next();
    };

    const deleteSelected = () => {
        const ids = [...selectedOnPage];

        setDeleteOpen(false);

        if (ids.length === 0) {
            return;
        }

        setSelected([]);
        setBusy('delete');
        setBulkError(null);

        let cursor = 0;

        const restoreFilters = () => {
            if (filters.q || filters.list) {
                router.get(
                    contactsIndex.url(slug),
                    {
                        q: filters.q ?? '',
                        list: filters.list ?? '',
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    },
                );
            }
        };

        const next = () => {
            if (cursor >= ids.length) {
                setBusy(null);
                restoreFilters();

                return;
            }

            const id = ids[cursor++];

            router.delete(destroy.url([slug, id]), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: next,
                onError: () => {
                    setBusy(null);
                    setBulkError('Could not delete every selected contact.');
                },
            });
        };

        next();
    };

    const importButton = can.create ? (
        <ImportContactsModal
            fields={fields}
            lists={listOptions}
            defaultListId={filters.list}
        >
            <Button variant="secondary" data-test="contacts-import-button">
                <Upload /> Import CSV
            </Button>
        </ImportContactsModal>
    ) : null;

    const addContactButton = can.create ? (
        <CreateContactModal
            lists={listOptions}
            statuses={statuses}
            defaultListIds={filters.list ? [filters.list] : undefined}
        >
            <Button data-test="contacts-new-button">
                <Plus /> Add contact
            </Button>
        </CreateContactModal>
    ) : null;

    const pagination = (
        <div className="flex items-center gap-2 pr-1 text-body leading-5 text-muted-foreground">
            <span className="hidden whitespace-nowrap sm:inline">
                {contacts.total === 0
                    ? '0 contacts'
                    : `Showing ${(contacts.from ?? 0).toLocaleString()}–${(contacts.to ?? 0).toLocaleString()} of ${contacts.total.toLocaleString()}`}
            </span>
            {previousLink?.url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={previousLink.url} preserveScroll>
                        Previous
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Previous
                </Button>
            )}
            {nextLink?.url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={nextLink.url} preserveScroll>
                        Next
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Next
                </Button>
            )}
        </div>
    );

    return (
        <>
            <Head title="Contacts" />

            <div className="flex h-svh min-h-[32rem] w-full min-w-0 flex-col px-4 pt-6 lg:px-6">
                <PageHead
                    title="Contacts"
                    description={`${contacts.total.toLocaleString()} ${
                        contacts.total === 1 ? 'contact' : 'contacts'
                    } in this view`}
                    actions={
                        can.create && !isFirstRun ? (
                            <>
                                {importButton}
                                {addContactButton}
                            </>
                        ) : null
                    }
                    mobileNav
                />

                {latestImport ? (
                    <div className="mt-4">
                        <ImportSummary contactImport={latestImport} />
                    </div>
                ) : null}

                <div
                    className="mt-6 flex flex-wrap items-center gap-2"
                    data-test="contacts-toolbar"
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">
                                <Filter /> Filters
                                {filters.list ? (
                                    <span
                                        className="size-1.5 rounded-full bg-primary"
                                        aria-hidden
                                    />
                                ) : null}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            <DropdownMenuLabel>Lists</DropdownMenuLabel>
                            <DropdownMenuRadioGroup
                                value={
                                    filters.list === null
                                        ? 'all'
                                        : String(filters.list)
                                }
                                onValueChange={(value) =>
                                    navigate({
                                        list:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <DropdownMenuRadioItem value="all">
                                    All contacts
                                </DropdownMenuRadioItem>
                                {lists.map((list) => (
                                    <DropdownMenuRadioItem
                                        key={list.id}
                                        value={String(list.id)}
                                    >
                                        <span className="truncate">
                                            {list.name}
                                        </span>
                                        <span className="ms-auto text-label text-muted-foreground tabular-nums">
                                            {list.contacts_count}
                                        </span>
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                            {hasFilters ? (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onSelect={clearFilters}>
                                        Clear filters
                                    </DropdownMenuItem>
                                </>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {selectedCount > 0 ? (
                        <>
                            <span
                                className="text-body leading-5 font-medium text-primary"
                                data-test="contacts-selection-count"
                            >
                                <span className="font-semibold tabular-nums">
                                    {selectedCount}
                                </span>{' '}
                                selected
                            </span>

                            <Button
                                variant="outline"
                                onClick={() => setCampaignOpen(true)}
                                disabled={busy !== null}
                                data-test="contacts-bulk-add-to-campaign"
                            >
                                <Send /> Add to campaign
                            </Button>
                            <Button
                                variant="outline"
                                onClick={markDoNotContact}
                                disabled={
                                    busy !== null || allAlreadyDoNotContact
                                }
                                data-test="contacts-bulk-do-not-contact"
                            >
                                Do not contact
                            </Button>
                            <Button
                                variant="outline"
                                className="text-destructive-foreground hover:bg-badge-danger hover:text-badge-danger-foreground"
                                onClick={() => setDeleteOpen(true)}
                                disabled={busy !== null}
                                data-test="contacts-bulk-delete"
                            >
                                <Trash2 /> Delete
                            </Button>
                        </>
                    ) : null}

                    <form
                        role="search"
                        className="ms-auto flex items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            navigate({ q: search });
                        }}
                    >
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search contacts"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search name or email…"
                                className="h-8 w-[246px] max-w-full pl-8"
                            />
                        </div>
                    </form>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Manage columns"
                                data-test="contacts-columns-button"
                            >
                                <Settings />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Columns</DropdownMenuLabel>
                            <DropdownMenuItem
                                disabled={!can.create}
                                onSelect={openNewField}
                                data-test="contacts-add-column"
                            >
                                <Plus /> Add column
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {bulkError ? (
                    <p
                        role="alert"
                        className="mt-2 text-body leading-5 text-destructive-foreground"
                    >
                        {bulkError}
                    </p>
                ) : null}

                <div className="mt-2 flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card">
                    {contacts.data.length === 0 ? (
                        <div className="flex min-h-0 flex-1 items-center justify-center p-6">
                            {isOutOfRange ? (
                                <EmptyState
                                    className="w-full max-w-lg border-0 py-16"
                                    title="This page has no contacts"
                                    description="The list has fewer pages than this one. Go back to the first page."
                                    actions={
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={contactsIndex.url(slug)}
                                            >
                                                Back to page 1
                                            </Link>
                                        </Button>
                                    }
                                />
                            ) : isFirstRun ? (
                                <EmptyState
                                    icon={Users}
                                    className="w-full max-w-lg border-0 py-16"
                                    title="No contacts yet"
                                    description="Add your first contact or import a CSV to start filling this grid."
                                    actions={
                                        can.create ? (
                                            <>
                                                {importButton}
                                                {addContactButton}
                                            </>
                                        ) : null
                                    }
                                />
                            ) : (
                                <EmptyState
                                    className="w-full max-w-lg border-0 py-16"
                                    title="No contacts match your filters"
                                    description="Try another search term or switch lists."
                                    actions={
                                        <Button
                                            variant="outline"
                                            onClick={clearFilters}
                                        >
                                            Clear filters
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    ) : (
                        <div className="flex min-h-0 flex-1 flex-col [&>[data-slot=table-container]]:min-h-0 [&>[data-slot=table-container]]:flex-1 [&>[data-slot=table-container]]:overflow-auto">
                            <ContactGrid
                                slug={slug}
                                contacts={contacts}
                                fields={fields}
                                statuses={statuses}
                                selected={selectedOnPage}
                                canManageFields={can.create}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                onEditField={openEditField}
                                onDeleteField={setDeletingField}
                            />
                        </div>
                    )}

                    <ListTabs
                        lists={lists}
                        activeListId={filters.list}
                        canCreate={can.create}
                        search={filters.q}
                        trailing={pagination}
                    />
                </div>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title={
                    selectedCount === 1
                        ? 'Delete 1 contact?'
                        : `Delete ${selectedCount} contacts?`
                }
                description={DELETE_COPY}
                confirmLabel={
                    selectedCount === 1
                        ? 'Delete contact'
                        : `Delete ${selectedCount} contacts`
                }
                onConfirm={deleteSelected}
                processing={busy === 'delete'}
            />

            {campaignOpen ? (
                <AddToCampaignModal
                    campaigns={campaigns}
                    contactIds={selectedOnPage}
                    open={campaignOpen}
                    onOpenChange={setCampaignOpen}
                    onSuccess={() => setSelected([])}
                />
            ) : null}

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

ContactsIndex.layout = () => ({
    showHeader: false,
});

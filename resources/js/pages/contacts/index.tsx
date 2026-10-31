import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Filter,
    MoreHorizontal,
    Plus,
    Search,
    Settings,
    Send,
    Trash2,
    Upload,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import { setFlashToastSuppressed } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
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
    const [deleteIds, setDeleteIds] = useState<number[]>([]);
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
        if (busy !== null) {
            return;
        }

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
        if (busy !== null) {
            return;
        }

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

    /**
     * Return to the list the user was looking at after a delete.
     *
     * Every successful DELETE redirects to the bare index, so the browser is on
     * unfiltered page 1 by the time a bulk loop ends. `force` visits even when
     * the target is the bare first page, because the failure path has to replace
     * that bare URL with the filtered one it started from.
     */
    const restoreAfterDelete = (
        deleted: number,
        force = false,
        onDone?: () => void,
    ) => {
        const perPage = contacts.per_page || 25;
        const remaining = Math.max(0, contacts.total - deleted);
        const lastPage = Math.max(1, Math.ceil(remaining / perPage));
        const targetPage = Math.min(contacts.current_page, lastPage);
        const needsVisit =
            force ||
            hasFilters ||
            targetPage !== 1 ||
            contacts.current_page !== 1;

        if (!needsVisit) {
            onDone?.();

            return;
        }

        router.get(
            contactsIndex.url(slug),
            {
                q: filters.q ?? '',
                list: filters.list ?? '',
                page: targetPage === 1 ? '' : String(targetPage),
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                async: true,
                onFinish: () => onDone?.(),
            },
        );
    };

    const runBulk = (kind: 'do-not-contact' | 'delete', ids: number[]) => {
        if (ids.length === 0) {
            setSelected([]);

            return;
        }

        const total = ids.length;
        let done = 0;
        let stopped = false;

        setBusy(kind);
        setBulkError(null);
        setFlashToastSuppressed(true);

        const finish = (failed: boolean) => {
            if (stopped) {
                return;
            }

            stopped = true;
            setBusy(null);
            setFlashToastSuppressed(false);

            const noun = total === 1 ? 'contact' : 'contacts';

            if (failed) {
                const message =
                    kind === 'delete'
                        ? `Deleted ${done} of ${total} ${noun}. The rest were not deleted.`
                        : `Marked ${done} of ${total} ${noun} as do not contact. The rest were not changed.`;

                setBulkError(message);
                toast.error(message);

                if (kind === 'delete') {
                    // The successful DELETEs moved the browser to the bare index;
                    // go back to the filtered page and page number the loop began
                    // on, then re-select what is left once that page has landed.
                    restoreAfterDelete(done, true, () =>
                        setSelected(ids.slice(done)),
                    );

                    return;
                }

                setSelected(ids.slice(done));

                // Reflect the rows that did change before the failure.
                router.reload({
                    only: ['contacts', 'lists'],
                    async: true,
                });

                return;
            }

            setSelected([]);
            toast.success(
                kind === 'delete'
                    ? done === 1
                        ? 'Deleted 1 contact.'
                        : `Deleted ${done} contacts.`
                    : done === 1
                      ? 'Marked 1 contact as do not contact.'
                      : `Marked ${done} contacts as do not contact.`,
            );

            if (kind === 'delete') {
                restoreAfterDelete(done);
            }
        };

        const next = (index: number) => {
            if (stopped) {
                return;
            }

            if (index >= ids.length) {
                finish(false);

                return;
            }

            let settled = false;

            const succeed = () => {
                if (settled) {
                    return;
                }

                settled = true;
                done += 1;
                next(index + 1);
            };

            const fail = () => {
                if (settled) {
                    return;
                }

                settled = true;
                finish(true);
            };

            const options = {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only: index === ids.length - 1 ? undefined : [],
                onSuccess: succeed,
                onError: fail,
                onHttpException: () => {
                    fail();

                    return false;
                },
                onNetworkError: () => {
                    fail();

                    return false;
                },
                onCancel: fail,
                onFinish: () => {
                    if (!settled) {
                        fail();
                    }
                },
            };

            try {
                if (kind === 'delete') {
                    router.delete(destroy.url([slug, ids[index]]), options);
                } else {
                    router.patch(
                        cell.url([slug, ids[index]]),
                        { field: 'status', value: 'DoNotContact' },
                        options,
                    );
                }
            } catch {
                fail();
            }
        };

        next(0);
    };

    const markDoNotContact = () => {
        const ids = selectedOnPage.filter(
            (id) =>
                contacts.data.find((contact) => contact.id === id)?.status !==
                'DoNotContact',
        );

        runBulk('do-not-contact', ids);
    };

    const deleteSelected = () => {
        const ids = [...deleteIds];

        setDeleteOpen(false);

        if (ids.length === 0) {
            return;
        }

        runBulk('delete', ids);
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

    // 390: the head keeps both actions, the import folded to its icon.
    const importButtonMobile = can.create ? (
        <ImportContactsModal
            fields={fields}
            lists={listOptions}
            defaultListId={filters.list}
        >
            <Button
                variant="secondary"
                size="icon"
                aria-label="Import CSV"
                data-test="contacts-import-button-mobile"
            >
                <Upload />
            </Button>
        </ImportContactsModal>
    ) : null;

    const addContactButtonMobile = can.create ? (
        <CreateContactModal
            lists={listOptions}
            statuses={statuses}
            defaultListIds={filters.list ? [filters.list] : undefined}
        >
            <Button
                aria-label="Add contact"
                data-test="contacts-new-button-mobile"
            >
                <Plus />
                <span>Add</span>
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
            {previousLink?.url && busy === null ? (
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
            {nextLink?.url && busy === null ? (
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

            <div className="flex min-h-[calc(100svh-3.5rem)] w-full min-w-0 flex-col px-4 pt-6 lg:h-svh lg:min-h-[32rem] lg:px-6">
                <PageHead
                    title="Contacts"
                    description={`${contacts.total.toLocaleString()} ${
                        contacts.total === 1 ? 'contact' : 'contacts'
                    } in this view`}
                    mobileDescription={`${contacts.total.toLocaleString()} ${
                        contacts.total === 1 ? 'contact' : 'contacts'
                    }`}
                    actions={
                        can.create && !isFirstRun ? (
                            <>
                                <div className="hidden items-center gap-2 lg:flex">
                                    {importButton}
                                    {addContactButton}
                                </div>
                                <div className="flex items-center gap-2 lg:hidden">
                                    {importButtonMobile}
                                    {addContactButtonMobile}
                                </div>
                            </>
                        ) : null
                    }
                    mobileNav
                />

                <div className="mt-4 lg:hidden">
                    <ListTabs
                        lists={lists}
                        activeListId={filters.list}
                        canCreate={can.create}
                        search={filters.q}
                        disabled={busy !== null}
                        className="h-auto rounded-lg border py-1"
                    />
                </div>

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
                            <Button
                                variant="outline"
                                disabled={busy !== null}
                                aria-label="Filters"
                            >
                                <Filter />
                                <span className="hidden lg:inline">
                                    Filters
                                </span>
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
                        <div className="hidden items-center gap-2 lg:flex">
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
                                onClick={() => {
                                    setDeleteIds(selectedOnPage);
                                    setDeleteOpen(true);
                                }}
                                disabled={busy !== null}
                                data-test="contacts-bulk-delete"
                            >
                                <Trash2 /> Delete
                            </Button>
                        </div>
                    ) : null}

                    <form
                        role="search"
                        className="flex min-w-0 flex-1 items-center gap-2 lg:ms-auto lg:flex-none"
                        onSubmit={(event) => {
                            event.preventDefault();
                            navigate({ q: search });
                        }}
                    >
                        <div className="relative min-w-0 flex-1 lg:flex-none">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search contacts"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search name or email…"
                                disabled={busy !== null}
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
                                disabled={busy !== null}
                                className="hidden lg:inline-flex"
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

                <div className="mt-2 flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card max-lg:-mx-4 max-lg:overflow-visible max-lg:rounded-none max-lg:border-x-0">
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
                        <div
                            aria-busy={busy !== null}
                            className={cn(
                                'flex min-h-0 flex-1 flex-col [&>[data-slot=table-container]]:min-h-0 [&>[data-slot=table-container]]:flex-1 [&>[data-slot=table-container]]:overflow-auto',
                                busy !== null &&
                                    'pointer-events-none opacity-60',
                            )}
                        >
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

                    <div className="hidden lg:block">
                        <ListTabs
                            lists={lists}
                            activeListId={filters.list}
                            canCreate={can.create}
                            search={filters.q}
                            disabled={busy !== null}
                            trailing={pagination}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-2 border-t px-4 py-2 lg:hidden">
                        <p className="text-label leading-4 text-muted-foreground">
                            {contacts.total === 0
                                ? '0 contacts'
                                : `${(contacts.from ?? 0).toLocaleString()}–${(contacts.to ?? 0).toLocaleString()} of ${contacts.total.toLocaleString()}`}
                        </p>
                        <div className="flex items-center gap-2">
                            {previousLink?.url && busy === null ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={previousLink.url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Previous
                                </Button>
                            )}
                            {nextLink?.url && busy === null ? (
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
                    </div>
                </div>
            </div>

            {selectedCount > 0 ? (
                <div
                    className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex min-h-14 items-center gap-3 border-t bg-background px-4 py-2.5 lg:hidden"
                    data-test="contacts-selection-bar"
                >
                    <p className="text-body leading-5 font-medium text-primary">
                        <span className="font-semibold tabular-nums">
                            {selectedCount}
                        </span>{' '}
                        selected
                    </p>
                    <Button
                        variant="secondary"
                        size="sm"
                        className="ms-auto"
                        onClick={() => setCampaignOpen(true)}
                        disabled={busy !== null}
                        data-test="contacts-bulk-add-to-campaign-mobile"
                    >
                        <Send /> Add to campaign
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="More contact actions"
                                data-test="contacts-bulk-more"
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={markDoNotContact}
                                disabled={
                                    busy !== null || allAlreadyDoNotContact
                                }
                            >
                                Do not contact
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => {
                                    setDeleteIds(selectedOnPage);
                                    setDeleteOpen(true);
                                }}
                                disabled={busy !== null}
                            >
                                <Trash2 /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ) : null}

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title={
                    deleteIds.length === 1
                        ? 'Delete 1 contact?'
                        : `Delete ${deleteIds.length} contacts?`
                }
                description={DELETE_COPY}
                confirmLabel={
                    deleteIds.length === 1
                        ? 'Delete contact'
                        : `Delete ${deleteIds.length} contacts`
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

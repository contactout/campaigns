import { router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CreateListModal from '@/components/contacts/create-list-modal';
import DeleteListModal from '@/components/contacts/delete-list-modal';
import EditListModal from '@/components/contacts/edit-list-modal';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/contacts';
import type { ContactListSummary } from '@/types';

type Props = {
    lists: ContactListSummary[];
    activeListId: number | null;
    canCreate: boolean;
    /** The current search, kept when switching lists. */
    search: string | null;
    /** A bulk action is running; block list navigation until it finishes. */
    disabled?: boolean;
    /** Pagination, rendered at the right of the strip. */
    trailing?: ReactNode;
};

export default function ListTabs({
    lists,
    activeListId,
    canCreate,
    search,
    disabled = false,
    trailing,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const activeList = lists.find((list) => list.id === activeListId) ?? null;

    const goToList = (listId: number | null) => {
        router.get(
            index.url(slug),
            { q: search ?? '', list: listId ?? '' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const tabClass = (isActive: boolean) =>
        cn(
            'relative flex h-[30px] shrink-0 items-center gap-1.5 rounded-md px-3 text-body leading-5 font-medium transition-colors focus-visible:ring-2 focus-visible:ring-primary-border focus-visible:outline-none disabled:opacity-50',
            isActive
                ? 'bg-accent text-primary after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
        );

    return (
        <div
            className="flex h-[35px] shrink-0 items-stretch justify-between gap-2 border-t bg-card"
            data-test="contacts-list-tabs"
        >
            <div className="flex min-w-0 items-end gap-1 overflow-x-auto px-1 pb-0.5">
                <button
                    type="button"
                    className={tabClass(activeListId === null)}
                    onClick={() => goToList(null)}
                    disabled={disabled}
                    data-test="list-tab-all"
                    aria-current={activeListId === null ? 'page' : undefined}
                >
                    All contacts
                </button>

                {lists.map((list) => (
                    <button
                        key={list.id}
                        type="button"
                        className={tabClass(activeListId === list.id)}
                        onClick={() => goToList(list.id)}
                        disabled={disabled}
                        data-test="list-tab"
                        aria-current={
                            activeListId === list.id ? 'page' : undefined
                        }
                    >
                        <span className="truncate">{list.name}</span>
                        <span
                            className={cn(
                                'text-label leading-4 tabular-nums',
                                activeListId === list.id
                                    ? 'text-primary/70'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {list.contacts_count}
                        </span>
                    </button>
                ))}

                {canCreate ? (
                    <CreateListModal>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mx-1 shrink-0 text-muted-foreground"
                            disabled={disabled}
                        >
                            <Plus /> Add list
                        </Button>
                    </CreateListModal>
                ) : null}
            </div>

            <div className="flex shrink-0 items-center gap-1 pr-2">
                {activeList ? (
                    <div className="flex items-center gap-0.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => setEditOpen(true)}
                            disabled={disabled}
                            data-test="list-rename-button"
                            aria-label={`Rename ${activeList.name}`}
                        >
                            <Pencil className="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => setDeleteOpen(true)}
                            disabled={disabled}
                            data-test="list-delete-button"
                            aria-label={`Delete ${activeList.name}`}
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </div>
                ) : null}

                {trailing}
            </div>

            {activeList && editOpen ? (
                <EditListModal
                    list={activeList}
                    open={editOpen}
                    onOpenChange={setEditOpen}
                />
            ) : null}

            {activeList && deleteOpen ? (
                <DeleteListModal
                    list={activeList}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            ) : null}
        </div>
    );
}

import { router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateListModal from '@/components/contacts/create-list-modal';
import DeleteListModal from '@/components/contacts/delete-list-modal';
import EditListModal from '@/components/contacts/edit-list-modal';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/contacts';
import type { ContactListSummary } from '@/types';

type Props = {
    lists: ContactListSummary[];
    activeListId: number | null;
    canCreate: boolean;
};

export default function ListTabs({ lists, activeListId, canCreate }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const activeList = lists.find((list) => list.id === activeListId) ?? null;

    const goToList = (listId: number | null) => {
        router.get(
            index.url(slug),
            { list: listId ?? '' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const tabClass = (isActive: boolean) =>
        [
            'flex h-10 shrink-0 items-center gap-2 border-t-2 px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            isActive
                ? 'border-primary bg-primary/5 text-foreground'
                : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
        ].join(' ');

    return (
        <div className="flex min-w-0 items-center justify-between border-t bg-card">
            <div className="flex min-w-0 items-center overflow-x-auto">
                <button
                    type="button"
                    className={tabClass(activeListId === null)}
                    onClick={() => goToList(null)}
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
                        data-test="list-tab"
                        aria-current={
                            activeListId === list.id ? 'page' : undefined
                        }
                    >
                        {list.name}
                        <span className="text-xs opacity-70">
                            {list.contacts_count}
                        </span>
                    </button>
                ))}

                {canCreate ? (
                    <CreateListModal>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mx-2 shrink-0"
                        >
                            <Plus className="h-4 w-4" /> New list
                        </Button>
                    </CreateListModal>
                ) : null}
            </div>

            {activeList ? (
                <div className="flex shrink-0 items-center gap-1 border-l px-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setEditOpen(true)}
                        data-test="list-rename-button"
                        aria-label={`Rename ${activeList.name}`}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleteOpen(true)}
                        data-test="list-delete-button"
                        aria-label={`Delete ${activeList.name}`}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ) : null}

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

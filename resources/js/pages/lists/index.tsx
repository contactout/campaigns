import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateListModal from '@/components/contacts/create-list-modal';
import DeleteListModal from '@/components/contacts/delete-list-modal';
import EditListModal from '@/components/contacts/edit-list-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show } from '@/routes/lists';
import type { ContactListSummary } from '@/types';

type Props = {
    lists: ContactListSummary[];
    can: {
        create: boolean;
    };
};

export default function ListsIndex({ lists, can }: Props) {
    const { currentTeam } = usePage().props;
    const [editing, setEditing] = useState<ContactListSummary | null>(null);
    const [deleting, setDeleting] = useState<ContactListSummary | null>(null);

    return (
        <>
            <Head title="Lists" />

            <h1 className="sr-only">Lists</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Lists"
                        description="Group contacts into reusable lists"
                    />

                    {can.create ? (
                        <CreateListModal>
                            <Button data-test="lists-new-button">
                                <Plus /> New list
                            </Button>
                        </CreateListModal>
                    ) : null}
                </div>

                {lists.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        No lists yet.
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {lists.map((list) => (
                            <Card key={list.id} data-test="list-card">
                                <CardHeader className="flex-row items-start justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <CardTitle>{list.name}</CardTitle>
                                        {list.is_default ? (
                                            <Badge variant="secondary">
                                                Default
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setEditing(list)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setDeleting(list)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        {list.contacts_count}{' '}
                                        {list.contacts_count === 1
                                            ? 'contact'
                                            : 'contacts'}
                                    </p>
                                    <Button variant="secondary" asChild>
                                        <Link
                                            href={show([
                                                currentTeam?.slug ?? '',
                                                list.id,
                                            ])}
                                        >
                                            View list
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {editing ? (
                <EditListModal
                    list={editing}
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                />
            ) : null}

            {deleting ? (
                <DeleteListModal
                    list={deleting}
                    open={deleting !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeleting(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}

ListsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Lists',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

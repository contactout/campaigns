import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AddContactsToListModal from '@/components/contacts/add-contacts-to-list-modal';
import DeleteListModal from '@/components/contacts/delete-list-modal';
import EditListModal from '@/components/contacts/edit-list-modal';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/lists';
import { detach } from '@/routes/lists/contacts';
import type {
    AvailableContact,
    ContactListSummary,
    ContactSummary,
    Paginated,
} from '@/types';

type Props = {
    list: ContactListSummary;
    contacts: Paginated<ContactSummary>;
    availableContacts: AvailableContact[];
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function ListShow({
    list,
    contacts,
    availableContacts,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [addOpen, setAddOpen] = useState(false);

    return (
        <>
            <Head title={list.name} />

            <h1 className="sr-only">{list.name}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Heading variant="small" title={list.name} />
                        {list.is_default ? (
                            <Badge variant="secondary">Default</Badge>
                        ) : null}
                    </div>

                    <div className="flex items-center gap-2">
                        {can.update ? (
                            <>
                                <Button
                                    variant="secondary"
                                    onClick={() => setAddOpen(true)}
                                >
                                    <Plus /> Add contacts
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setEditOpen(true)}
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                            </>
                        ) : null}
                        {can.delete ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        ) : null}
                    </div>
                </div>

                {contacts.data.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        No contacts in this list yet.
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
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.data.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className="border-t"
                                        data-test="list-contact-row"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {contact.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {contact.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.status_label}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {can.update ? (
                                                <Form
                                                    {...detach.form([
                                                        slug,
                                                        list.id,
                                                        contact.id,
                                                    ])}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </Form>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={contacts.links} />

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={index(slug)}>Back to lists</Link>
                    </Button>
                </div>
            </div>

            {editOpen ? (
                <EditListModal
                    list={list}
                    open={editOpen}
                    onOpenChange={setEditOpen}
                />
            ) : null}

            {deleteOpen ? (
                <DeleteListModal
                    list={list}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            ) : null}

            {addOpen ? (
                <AddContactsToListModal
                    list={list}
                    availableContacts={availableContacts}
                    open={addOpen}
                    onOpenChange={setAddOpen}
                />
            ) : null}
        </>
    );
}

ListShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Lists',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

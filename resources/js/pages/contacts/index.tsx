import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
    const [fieldModalOpen, setFieldModalOpen] = useState(false);
    const [editingField, setEditingField] = useState<ContactField | null>(null);
    const [deletingField, setDeletingField] = useState<ContactField | null>(
        null,
    );

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

    return (
        <>
            <Head title="Contacts" />

            <h1 className="sr-only">Contacts</h1>

            <div className="flex h-full flex-1 flex-col gap-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Contacts"
                        description="People you reach out to"
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
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {contacts.data.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center">
                        <h3 className="font-semibold">No contacts yet</h3>
                        <p className="text-sm text-muted-foreground">
                            Add your first contact, or import a list later.
                        </p>
                        {can.create ? (
                            <CreateContactModal
                                lists={listOptions}
                                statuses={statuses}
                                defaultListIds={
                                    filters.list ? [filters.list] : undefined
                                }
                            >
                                <Button className="mt-2">
                                    <Plus /> New contact
                                </Button>
                            </CreateContactModal>
                        ) : null}
                    </div>
                ) : (
                    <div className="min-h-0 flex-1 overflow-auto rounded-lg border">
                        <table className="w-full border-collapse text-sm">
                            <thead className="sticky top-0 z-10 bg-muted text-left">
                                <tr>
                                    <th className="border-r border-b px-4 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="border-r border-b px-4 py-2 font-medium">
                                        Email
                                    </th>
                                    <th className="border-r border-b px-4 py-2 font-medium">
                                        Phone
                                    </th>
                                    <th className="border-r border-b px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="border-r border-b px-4 py-2 font-medium">
                                        Lists
                                    </th>
                                    {fields.map((field) => (
                                        <th
                                            key={field.id}
                                            className="group border-r border-b px-4 py-2 font-medium"
                                        >
                                            <span className="flex items-center gap-1">
                                                {field.name}
                                                {can.create ? (
                                                    <span className="flex items-center opacity-0 group-hover:opacity-100">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openEditField(
                                                                    field,
                                                                )
                                                            }
                                                            aria-label={`Edit ${field.name}`}
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setDeletingField(
                                                                    field,
                                                                )
                                                            }
                                                            aria-label={`Delete ${field.name}`}
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    </span>
                                                ) : null}
                                            </span>
                                        </th>
                                    ))}
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
                                        {fields.map((field) => (
                                            <td
                                                key={field.id}
                                                className="border-r px-2 py-1 text-muted-foreground"
                                            >
                                                <InlinePropertyCell
                                                    contactId={contact.id}
                                                    fieldId={field.id}
                                                    value={
                                                        contact.properties[
                                                            field.id
                                                        ] ?? null
                                                    }
                                                />
                                            </td>
                                        ))}
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

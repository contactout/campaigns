import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ContactStatusBadge from '@/components/contacts/contact-status-badge';
import DeleteContactModal from '@/components/contacts/delete-contact-modal';
import EditContactModal from '@/components/contacts/edit-contact-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/contacts';
import type {
    ContactDetail,
    ContactField,
    ContactRecipient,
    ListOption,
    StatusOption,
} from '@/types';

type Props = {
    contact: ContactDetail;
    lists: ListOption[];
    list_ids: number[];
    allLists: ListOption[];
    fields: ContactField[];
    properties: Record<string, string>;
    recipients: ContactRecipient[];
    statuses: StatusOption[];
};

export default function ContactShow({
    contact,
    lists,
    list_ids,
    allLists,
    fields,
    properties,
    recipients,
    statuses,
}: Props) {
    const { currentTeam } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title={contact.name} />

            <h1 className="sr-only">{contact.name}</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Heading variant="small" title={contact.name} />
                        <ContactStatusBadge
                            status={contact.status}
                            label={contact.status_label}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setEditOpen(true)}
                            data-test="contact-edit-button"
                        >
                            <Pencil /> Edit
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => setDeleteOpen(true)}
                            data-test="contact-delete-button"
                        >
                            <Trash2 /> Delete
                        </Button>
                    </div>
                </div>

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Email" value={contact.email} />
                        <Detail label="Phone" value={contact.phone} />
                        <Detail label="Timezone" value={contact.timezone} />
                        <Detail label="Source" value={contact.source} />
                        <Detail
                            label="Do not contact"
                            value={contact.do_not_contact ? 'Yes' : 'No'}
                        />
                    </CardContent>
                </Card>

                {fields.length > 0 ? (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Custom fields</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {fields.map((field) => (
                                <Detail
                                    key={field.id}
                                    label={field.name}
                                    value={properties[field.id] ?? null}
                                />
                            ))}
                        </CardContent>
                    </Card>
                ) : null}

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Lists</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {lists.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Not in any list.
                            </p>
                        ) : (
                            lists.map((list) => (
                                <Badge key={list.id} variant="secondary">
                                    {list.name}
                                </Badge>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Campaigns</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recipients.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                This contact is not in any campaign.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recipients.map((recipient) => (
                                    <li
                                        key={recipient.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                                    >
                                        <span>{recipient.campaign}</span>
                                        <span className="text-muted-foreground">
                                            {recipient.status_label}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div>
                    <Button variant="ghost" asChild>
                        <Link
                            href={index(currentTeam?.slug ?? '')}
                            data-test="contacts-back-link"
                        >
                            Back to contacts
                        </Link>
                    </Button>
                </div>
            </div>

            <EditContactModal
                contact={contact}
                lists={allLists}
                listIds={list_ids}
                statuses={statuses}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteContactModal
                contact={contact}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid gap-1">
            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span>{value ?? '—'}</span>
        </div>
    );
}

ContactShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Contacts',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

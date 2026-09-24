import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConnectionFormModal from '@/components/mailer-connections/connection-form-modal';
import DeleteConnectionModal from '@/components/mailer-connections/delete-connection-modal';
import MailerConnectionStatusBadge from '@/components/mailer-connections/status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index, verify } from '@/routes/mailer-connections';
import type { MailerConnection } from '@/types';

type Props = {
    connections: MailerConnection[];
    can: {
        create: boolean;
    };
};

export default function MailerConnectionsIndex({ connections, can }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<MailerConnection | null>(null);
    const [deleting, setDeleting] = useState<MailerConnection | null>(null);

    const openNew = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (connection: MailerConnection) => {
        setEditing(connection);
        setFormOpen(true);
    };

    return (
        <>
            <Head title="Sending connections" />

            <h1 className="sr-only">Sending connections</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Sending connections"
                        description="SMTP accounts used to send your campaigns"
                    />

                    {can.create ? (
                        <Button onClick={openNew} data-test="connection-new">
                            <Plus /> New connection
                        </Button>
                    ) : null}
                </div>

                {connections.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <h3 className="font-semibold">No connections yet</h3>
                        <p className="text-sm text-muted-foreground">
                            Add an SMTP account to start sending campaigns.
                        </p>
                        {can.create ? (
                            <Button className="mt-2" onClick={openNew}>
                                <Plus /> New connection
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Server
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        From
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Sent
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {connections.map((connection) => (
                                    <tr
                                        key={connection.id}
                                        className="border-t"
                                        data-test="connection-row"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {connection.name}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {connection.host}:{connection.port}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {connection.from_email}
                                        </td>
                                        <td className="px-4 py-2">
                                            <MailerConnectionStatusBadge
                                                status={connection.status}
                                                label={connection.status_label}
                                            />
                                            {connection.last_error ? (
                                                <p className="mt-1 max-w-xs truncate text-xs text-destructive">
                                                    {connection.last_error}
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {connection.sent_count}
                                            {connection.sending_limit
                                                ? ` / ${connection.sending_limit}`
                                                : ''}
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="flex items-center justify-end gap-1">
                                                <Form
                                                    {...verify.form([
                                                        slug,
                                                        connection.id,
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
                                                            <RefreshCw className="h-4 w-4" />
                                                            Verify
                                                        </Button>
                                                    )}
                                                </Form>
                                                {can.create ? (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                openEdit(
                                                                    connection,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setDeleting(
                                                                    connection,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {formOpen ? (
                <ConnectionFormModal
                    connection={editing}
                    open={formOpen}
                    onOpenChange={setFormOpen}
                />
            ) : null}

            {deleting ? (
                <DeleteConnectionModal
                    connection={deleting}
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

MailerConnectionsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Sending connections',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

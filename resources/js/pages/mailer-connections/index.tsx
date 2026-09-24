import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConnectionFormModal from '@/components/mailer-connections/connection-form-modal';
import DeleteConnectionModal from '@/components/mailer-connections/delete-connection-modal';
import MailerConnectionStatusBadge from '@/components/mailer-connections/status-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, verify } from '@/routes/mailer-connections';
import { redirect as oauthRedirect } from '@/routes/mailer-connections/oauth';
import type { MailerConnection } from '@/types';

type Props = {
    connections: MailerConnection[];
    oauth: {
        gmail: boolean;
        outlook: boolean;
    };
    can: {
        create: boolean;
    };
};

function isSmtp(connection: MailerConnection): boolean {
    return connection.mailer_type === 'Smtp';
}

function serverLabel(connection: MailerConnection): string {
    if (!isSmtp(connection)) {
        return connection.mailer_type_label;
    }

    if (connection.host) {
        return `${connection.host}:${connection.port ?? ''}`;
    }

    return '—';
}

export default function MailerConnectionsIndex({
    connections,
    oauth,
    can,
}: Props) {
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
        if (!isSmtp(connection)) {
            return;
        }

        setEditing(connection);
        setFormOpen(true);
    };

    return (
        <>
            <Head title="Connections" />

            <h1 className="sr-only">Connections</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Connections"
                        description="SMTP, Gmail, or Outlook accounts used to send your campaigns"
                    />

                    {can.create ? (
                        <div className="flex flex-wrap items-center gap-2">
                            {oauth.gmail ? (
                                <Button
                                    variant="secondary"
                                    asChild
                                    data-test="connection-gmail"
                                >
                                    <a
                                        href={oauthRedirect.url([
                                            slug,
                                            'gmail',
                                        ])}
                                    >
                                        Connect Gmail
                                    </a>
                                </Button>
                            ) : null}
                            {oauth.outlook ? (
                                <Button
                                    variant="secondary"
                                    asChild
                                    data-test="connection-outlook"
                                >
                                    <a
                                        href={oauthRedirect.url([
                                            slug,
                                            'outlook',
                                        ])}
                                    >
                                        Connect Outlook
                                    </a>
                                </Button>
                            ) : null}
                            <Button
                                onClick={openNew}
                                data-test="connection-new"
                            >
                                <Plus /> New SMTP
                            </Button>
                        </div>
                    ) : null}
                </div>

                {connections.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <h3 className="font-semibold">No connections yet</h3>
                        <p className="text-sm text-muted-foreground">
                            Connect Gmail or Outlook, or add an SMTP account to
                            start sending campaigns.
                        </p>
                        {can.create ? (
                            <div className="mt-2 flex flex-wrap justify-center gap-2">
                                {oauth.gmail ? (
                                    <Button variant="secondary" asChild>
                                        <a
                                            href={oauthRedirect.url([
                                                slug,
                                                'gmail',
                                            ])}
                                        >
                                            Connect Gmail
                                        </a>
                                    </Button>
                                ) : null}
                                {oauth.outlook ? (
                                    <Button variant="secondary" asChild>
                                        <a
                                            href={oauthRedirect.url([
                                                slug,
                                                'outlook',
                                            ])}
                                        >
                                            Connect Outlook
                                        </a>
                                    </Button>
                                ) : null}
                                <Button onClick={openNew}>
                                    <Plus /> New SMTP
                                </Button>
                            </div>
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
                                        Type
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
                                        <td className="px-4 py-2">
                                            <Badge variant="outline">
                                                {connection.mailer_type_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {serverLabel(connection)}
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
                                                {can.create &&
                                                !isSmtp(connection) ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={oauthRedirect.url(
                                                                [
                                                                    slug,
                                                                    connection.mailer_type.toLowerCase(),
                                                                ],
                                                            )}
                                                        >
                                                            Reconnect
                                                        </a>
                                                    </Button>
                                                ) : null}
                                                {can.create &&
                                                isSmtp(connection) ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(connection)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                ) : null}
                                                {can.create ? (
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
            title: 'Connections',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

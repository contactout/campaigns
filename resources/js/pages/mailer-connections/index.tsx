import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    CircleAlert,
    Mail,
    MoreVertical,
    Pencil,
    Plus,
    Plug,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHead from '@/components/page-head';
import CapMeter from '@/components/mailer-connections/cap-meter';
import ConnectionFormModal from '@/components/mailer-connections/connection-form-modal';
import DeleteConnectionModal from '@/components/mailer-connections/delete-connection-modal';
import SignatureSelect from '@/components/mailer-connections/signature-select';
import MailerConnectionStatusBadge from '@/components/mailer-connections/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index, verify } from '@/routes/mailer-connections';
import { redirect as oauthRedirect } from '@/routes/mailer-connections/oauth';
import type {
    MailerConnection,
    MailerConnectionSignatureOption,
} from '@/types';

type Props = {
    connections: MailerConnection[];
    signatures: MailerConnectionSignatureOption[];
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
    if (connection.host) {
        return connection.port
            ? `${connection.host}:${connection.port}`
            : connection.host;
    }

    // An OAuth connection keeps no SMTP host, so the provider name is the only
    // server it has.
    return connection.mailer_type_label;
}

/** `Retry` is the existing verify action, restyled. */
function RetryButton({ connection }: { connection: MailerConnection }) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    return (
        <Form
            {...verify.form([slug, connection.id])}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={processing}
                    data-test="connection-retry"
                >
                    <RefreshCw /> Retry
                </Button>
            )}
        </Form>
    );
}

export default function MailerConnectionsIndex({
    connections,
    signatures,
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

    const failed = connections.filter(
        (connection) => connection.display_status === 'Failed',
    );

    // The two OAuth providers stay separate routes; the board's one primary
    // button routes to either, and collapses to a plain link when only one
    // provider is configured.
    const oauthProviders = [
        ...(oauth.gmail ? (['gmail'] as const) : []),
        ...(oauth.outlook ? (['outlook'] as const) : []),
    ];

    const oauthLabel = (provider: 'gmail' | 'outlook') =>
        provider === 'gmail' ? 'Connect Gmail' : 'Connect Outlook';

    const connectButton =
        oauthProviders.length === 0 ? null : oauthProviders.length === 1 ? (
            <Button asChild data-test={`connection-${oauthProviders[0]}`}>
                <a href={oauthRedirect.url([slug, oauthProviders[0]])}>
                    <Mail />
                    Connect Gmail or Outlook
                </a>
            </Button>
        ) : (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button data-test="connection-oauth">
                        <Mail /> Connect Gmail or Outlook
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {oauthProviders.map((provider) => (
                        <DropdownMenuItem
                            key={provider}
                            asChild
                            data-test={`connection-${provider}`}
                        >
                            <a href={oauthRedirect.url([slug, provider])}>
                                {oauthLabel(provider)}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );

    return (
        <>
            <Head title="Connections" />

            <div className="flex w-full flex-col gap-5 px-4 py-6 lg:px-6">
                <PageHead
                    title="Connections"
                    description="SMTP, Gmail, or Outlook accounts used to send your campaigns"
                    mobileNav
                    actions={
                        can.create ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={openNew}
                                    data-test="connection-new"
                                >
                                    <Plus /> Add SMTP
                                </Button>
                                {connectButton}
                            </>
                        ) : null
                    }
                />

                {connections.length === 0 ? (
                    <EmptyState
                        icon={Plug}
                        title="No connections yet"
                        description="Connect Gmail or Outlook, or add an SMTP account to start sending campaigns."
                        actions={
                            can.create ? (
                                <>
                                    <Button variant="outline" onClick={openNew}>
                                        <Plus /> Add SMTP
                                    </Button>
                                    {connectButton}
                                </>
                            ) : null
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-3">
                        {failed.map((connection) => (
                            <div
                                key={connection.id}
                                role="alert"
                                className="flex items-center gap-3 rounded-lg border border-destructive/20 bg-badge-danger px-4 py-2.5 text-body leading-5 text-badge-danger-foreground"
                                data-test="connection-failure-banner"
                            >
                                <CircleAlert
                                    className="size-4 shrink-0"
                                    aria-hidden
                                />
                                <p className="min-w-0 flex-1">
                                    {connection.name} failed verification
                                    {connection.last_error_message
                                        ? `: ${connection.last_error_message}`
                                        : '.'}
                                </p>
                                {can.create ? (
                                    <RetryButton connection={connection} />
                                ) : null}
                            </div>
                        ))}

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full table-fixed text-body">
                                <colgroup>
                                    <col />
                                    <col className="w-[91px]" />
                                    <col className="w-[150px]" />
                                    <col className="w-[170px]" />
                                    <col className="w-[109px]" />
                                    <col className="w-[100px]" />
                                    <col className="w-[143px]" />
                                    <col className="w-[72px]" />
                                </colgroup>
                                <thead className="bg-muted/50 text-left">
                                    <tr className="border-b">
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Name
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Type
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Server
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            From
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Signature
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Status
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Sent today
                                        </th>
                                        <th className="h-[33px] pr-8 pl-0">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {connections.map((connection) => (
                                        <tr
                                            key={connection.id}
                                            className="h-11 border-b last:border-b-0 hover:bg-muted/30"
                                            data-test="connection-row"
                                        >
                                            <td className="px-4 text-body leading-5 font-medium">
                                                <span
                                                    className="block truncate"
                                                    title={connection.name}
                                                >
                                                    {connection.name}
                                                </span>
                                            </td>
                                            <td className="px-4">
                                                <Badge variant="neutral">
                                                    {
                                                        connection.mailer_type_label
                                                    }
                                                </Badge>
                                            </td>
                                            <td className="px-4 text-body leading-5">
                                                <span
                                                    className="block truncate"
                                                    title={serverLabel(
                                                        connection,
                                                    )}
                                                >
                                                    {serverLabel(connection)}
                                                </span>
                                            </td>
                                            <td className="px-4 text-body leading-5">
                                                <span
                                                    className="block truncate"
                                                    title={
                                                        connection.from_email ??
                                                        undefined
                                                    }
                                                >
                                                    {connection.from_email ??
                                                        '—'}
                                                </span>
                                            </td>
                                            <td className="px-4">
                                                <SignatureSelect
                                                    connection={connection}
                                                    signatures={signatures}
                                                    disabled={!can.create}
                                                />
                                            </td>
                                            <td className="px-4">
                                                <MailerConnectionStatusBadge
                                                    status={
                                                        connection.display_status
                                                    }
                                                    label={
                                                        connection.display_status_label
                                                    }
                                                />
                                            </td>
                                            <td className="px-4">
                                                <CapMeter
                                                    sentToday={
                                                        connection.sent_today
                                                    }
                                                    sendingLimit={
                                                        connection.sending_limit
                                                    }
                                                />
                                            </td>
                                            <td className="pr-8 pl-0 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-muted-foreground"
                                                            aria-label={`Actions for ${connection.name}`}
                                                        >
                                                            <MoreVertical className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        {can.create ? (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        verify.url(
                                                                            [
                                                                                slug,
                                                                                connection.id,
                                                                            ],
                                                                        ),
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw />
                                                                Verify
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        {can.create &&
                                                        !isSmtp(connection) ? (
                                                            <DropdownMenuItem
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
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        {can.create &&
                                                        isSmtp(connection) ? (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    openEdit(
                                                                        connection,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        {can.create ? (
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={() =>
                                                                    setDeleting(
                                                                        connection,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="mt-0.5 text-label leading-4 text-muted-foreground">
                            Daily sending caps reset at 00:00 UTC.
                        </p>
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
    showHeader: false,
});

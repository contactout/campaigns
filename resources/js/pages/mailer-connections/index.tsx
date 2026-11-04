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
import CapMeter, { capText } from '@/components/mailer-connections/cap-meter';
import ConnectionFormModal from '@/components/mailer-connections/connection-form-modal';
import DeleteConnectionModal from '@/components/mailer-connections/delete-connection-modal';
import SignatureSelect, {
    assignSignature,
    TEAM_DEFAULT,
    signatureOptionLabel,
    signatureValue,
} from '@/components/mailer-connections/signature-select';
import MailerConnectionStatusBadge from '@/components/mailer-connections/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
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

/** Pending connections and failed probes use the same verification action. */
function VerifyButton({
    connection,
    retry = false,
}: {
    connection: MailerConnection;
    retry?: boolean;
}) {
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
                    data-test={retry ? 'connection-retry' : 'connection-verify'}
                >
                    <RefreshCw /> {retry ? 'Retry' : 'Verify'}
                </Button>
            )}
        </Form>
    );
}

/**
 * Verify, Reconnect, Edit and Delete, shared by the table row and the 390 card.
 *
 * The board's cards have no overflow menu; keeping one is what makes the
 * destructive actions reachable on a phone (the table's only other route to
 * them is a hover-sized kebab).
 */
function ConnectionMenu({
    connection,
    canCreate,
    onEdit,
    onDelete,
    signatures,
}: {
    connection: MailerConnection;
    canCreate: boolean;
    /**
     * Set only by the table: it hides the Signature column below xl, so the
     * menu carries the signature setting there. The cards have their own select.
     */
    signatures?: MailerConnectionSignatureOption[];
    onEdit: (connection: MailerConnection) => void;
    onDelete: (connection: MailerConnection) => void;
}) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
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
                {canCreate ? (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                verify.url([slug, connection.id]),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw /> Verify
                    </DropdownMenuItem>
                ) : null}
                {canCreate && !isSmtp(connection) ? (
                    <DropdownMenuItem asChild>
                        <a
                            href={oauthRedirect.url([
                                slug,
                                connection.mailer_type.toLowerCase(),
                            ])}
                        >
                            Reconnect
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {canCreate && isSmtp(connection) ? (
                    <DropdownMenuItem onSelect={() => onEdit(connection)}>
                        <Pencil /> Edit
                    </DropdownMenuItem>
                ) : null}
                {canCreate && signatures ? (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger
                            className="xl:hidden"
                            data-test="connection-menu-signature"
                        >
                            Signature
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <DropdownMenuRadioGroup
                                value={signatureValue(connection)}
                                onValueChange={(next) =>
                                    assignSignature(slug, connection, next)
                                }
                            >
                                <DropdownMenuRadioItem value={TEAM_DEFAULT}>
                                    Team default
                                </DropdownMenuRadioItem>
                                {signatures.map((signature) => (
                                    <DropdownMenuRadioItem
                                        key={signature.id}
                                        value={String(signature.id)}
                                    >
                                        {signatureOptionLabel(signature)}
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                ) : null}
                {canCreate ? (
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => onDelete(connection)}
                    >
                        <Trash2 /> Delete
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
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
                    mobileDescription={`${connections.length} sending ${
                        connections.length === 1 ? 'account' : 'accounts'
                    }`}
                    mobileNav
                    actions={
                        can.create ? (
                            <>
                                <div className="hidden items-center gap-2 lg:flex">
                                    <Button
                                        variant="outline"
                                        onClick={openNew}
                                        data-test="connection-new"
                                    >
                                        <Plus /> Add SMTP
                                    </Button>
                                    {connectButton}
                                </div>
                                <div className="lg:hidden">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                aria-label="Add connection"
                                                data-test="connection-new-mobile"
                                            >
                                                <Plus /> Add
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onSelect={openNew}
                                            >
                                                <Plus /> Add SMTP
                                            </DropdownMenuItem>
                                            {oauthProviders.map((provider) => (
                                                <DropdownMenuItem
                                                    key={provider}
                                                    asChild
                                                    data-test={`connection-mobile-${provider}`}
                                                >
                                                    <a
                                                        href={oauthRedirect.url(
                                                            [slug, provider],
                                                        )}
                                                    >
                                                        <Mail />
                                                        {oauthLabel(provider)}
                                                    </a>
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
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
                                    <VerifyButton
                                        connection={connection}
                                        retry
                                    />
                                ) : null}
                            </div>
                        ))}

                        {/* 390: one card per connection, `Sent today` as text. */}
                        <ul
                            className="flex flex-col gap-3 lg:hidden"
                            data-test="connection-card-list"
                        >
                            {connections.map((connection) => (
                                <li
                                    key={connection.id}
                                    className="rounded-lg border bg-card p-4"
                                    data-test="connection-card"
                                >
                                    <div className="flex items-center gap-2">
                                        <p className="min-w-0 flex-1 truncate text-body leading-5 font-semibold">
                                            {connection.name}
                                        </p>
                                        <MailerConnectionStatusBadge
                                            status={connection.display_status}
                                            label={
                                                connection.display_status_label
                                            }
                                        />
                                        <ConnectionMenu
                                            connection={connection}
                                            canCreate={can.create}
                                            onEdit={openEdit}
                                            onDelete={setDeleting}
                                        />
                                    </div>

                                    <p className="mt-1 truncate text-label leading-4 text-muted-foreground">
                                        {connection.from_email ?? '—'} ·{' '}
                                        {connection.mailer_type_label}
                                    </p>
                                    <div className="flex min-w-0 items-center gap-1 text-label leading-4 text-muted-foreground">
                                        <span className="truncate">
                                            {serverLabel(connection)}
                                        </span>
                                        <span aria-hidden>·</span>
                                        <span className="shrink-0">
                                            Signature:
                                        </span>
                                        <SignatureSelect
                                            connection={connection}
                                            signatures={signatures}
                                            disabled={!can.create}
                                            triggerClassName="max-lg:w-auto max-lg:flex-initial text-label leading-4 text-muted-foreground"
                                        />
                                    </div>

                                    {can.create &&
                                    connection.display_status === 'Pending' ? (
                                        <div className="mt-3">
                                            <VerifyButton
                                                connection={connection}
                                            />
                                        </div>
                                    ) : null}

                                    <div className="mt-3 flex items-center justify-between gap-3">
                                        <span className="text-label leading-4 text-muted-foreground">
                                            Sent today
                                        </span>
                                        <span className="text-body leading-5 font-semibold tabular-nums">
                                            {capText(
                                                connection.sent_today,
                                                connection.sending_limit,
                                            )}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto rounded-lg border lg:block">
                            <table className="w-full table-fixed text-body">
                                <colgroup>
                                    <col className="w-[140px]" />
                                    <col className="hidden w-[91px] xl:table-column" />
                                    <col />
                                    <col className="w-[150px]" />
                                    <col className="hidden w-[109px] xl:table-column" />
                                    <col className="w-[132px]" />
                                    <col className="w-[120px]" />
                                    <col className="w-[72px]" />
                                </colgroup>
                                <thead className="bg-muted/50 text-left">
                                    <tr className="border-b">
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Name
                                        </th>
                                        <th className="hidden h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground xl:table-cell">
                                            Type
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            Server
                                        </th>
                                        <th className="h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground">
                                            From
                                        </th>
                                        <th className="hidden h-[33px] px-4 text-label leading-4 font-medium text-muted-foreground xl:table-cell">
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
                                            <td className="hidden px-4 xl:table-cell">
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
                                            <td className="hidden py-0 pr-0 pl-4 xl:table-cell">
                                                <SignatureSelect
                                                    connection={connection}
                                                    signatures={signatures}
                                                    disabled={!can.create}
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="flex flex-col items-start gap-2">
                                                    <MailerConnectionStatusBadge
                                                        status={
                                                            connection.display_status
                                                        }
                                                        label={
                                                            connection.display_status_label
                                                        }
                                                    />
                                                    {can.create &&
                                                    connection.display_status ===
                                                        'Pending' ? (
                                                        <VerifyButton
                                                            connection={
                                                                connection
                                                            }
                                                        />
                                                    ) : null}
                                                </div>
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
                                                <ConnectionMenu
                                                    connection={connection}
                                                    canCreate={can.create}
                                                    signatures={signatures}
                                                    onEdit={openEdit}
                                                    onDelete={setDeleting}
                                                />
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

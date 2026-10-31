import { Head, Link, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    FileText,
    Plug,
    Plus,
    Send,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import CreateCampaignModal from '@/components/campaigns/create-campaign-modal';
import PageHead from '@/components/page-head';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as connectionsIndex } from '@/routes/mailer-connections';
import { index as teamsIndex } from '@/routes/teams';
import { index as templatesIndex } from '@/routes/templates';
import type {
    DashboardInvitation,
    DashboardJumpBackIn,
    DashboardMetrics,
    DashboardSetup,
    MailerConnectionOption,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    setup?: DashboardSetup | null;
    metrics?: DashboardMetrics | null;
    jumpBackIn?: DashboardJumpBackIn | null;
    mailerConnections?: MailerConnectionOption[];
    can?: {
        create: boolean;
    };
};

type Delta = {
    value: number;
    unit: '%' | 'pt';
};

const numberFormatter = new Intl.NumberFormat();
const dateFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
});

const plural = (count: number, singular: string, pluralForm: string) =>
    count === 1 ? singular : pluralForm;

const formatCount = (value: number) => numberFormatter.format(value);

const formatRate = (value: number | null | undefined) =>
    value === null || value === undefined ? '—' : `${value.toFixed(1)}%`;

const formatDelta = (delta: Delta) => {
    const magnitude = Math.abs(delta.value).toFixed(1).replace(/\.0$/, '');
    const sign = delta.value < 0 ? '-' : '+';

    return `${sign}${magnitude}${delta.unit}`;
};

function StatCard({
    label,
    value,
    delta,
}: {
    label: string;
    value: string;
    delta?: Delta | null;
}) {
    return (
        <div className="rounded-lg border bg-card px-4 py-4">
            <p className="text-[12px] leading-4 font-medium text-muted-foreground">
                {label}
            </p>
            <div className="mt-1.5 flex items-baseline gap-2">
                <p className="text-[20px] leading-7 font-bold tracking-[-0.01em]">
                    {value}
                </p>
                {delta ? (
                    <span
                        className={cn(
                            'text-[12px] leading-4 font-medium',
                            delta.value > 0
                                ? 'text-success'
                                : delta.value < 0
                                  ? 'text-destructive'
                                  : 'text-muted-foreground',
                        )}
                    >
                        {formatDelta(delta)}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    setup = null,
    metrics = null,
    jumpBackIn = null,
    mailerConnections = [],
    can,
}: Props) {
    const { auth, currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(false);

    const slug = currentTeam?.slug ?? null;
    const firstName = auth.user.name.trim().split(/\s+/)[0] ?? '';
    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? 'Good morning'
            : hour < 18
              ? 'Good afternoon'
              : 'Good evening';

    const checklist = [
        {
            key: 'connection',
            done: setup?.has_connection ?? false,
            title: 'Connect an inbox',
            description:
                'Link Gmail, Outlook, or SMTP so you can send campaigns.',
            href: slug ? connectionsIndex(slug) : teamsIndex(),
            cta: 'Go to Connections',
        },
        {
            key: 'contact',
            done: setup?.has_contact ?? false,
            title: 'Add contacts',
            description: 'Build your audience before you launch a sequence.',
            href: slug ? contactsIndex(slug) : teamsIndex(),
            cta: 'Go to Contacts',
        },
        {
            key: 'campaign',
            done: setup?.has_campaign ?? false,
            title: 'Create a campaign',
            description: 'Write your first email sequence and add recipients.',
            href: slug ? campaignsIndex(slug) : teamsIndex(),
            cta: 'Go to Campaigns',
        },
    ];

    const doneCount = checklist.filter((step) => step.done).length;

    const invitationNames = pendingInvitations
        .map((invitation) => invitation.team.name)
        .join(', ');
    const invitationCount = pendingInvitations.length;

    const destinations = [
        {
            title: 'Contacts',
            icon: Users,
            href: slug ? contactsIndex(slug) : teamsIndex(),
            qualifier: jumpBackIn
                ? `${formatCount(jumpBackIn.contacts)} ${plural(jumpBackIn.contacts, 'contact', 'contacts')} · ${formatCount(jumpBackIn.lists)} ${plural(jumpBackIn.lists, 'list', 'lists')}`
                : null,
        },
        {
            title: 'Campaigns',
            icon: Send,
            href: slug ? campaignsIndex(slug) : teamsIndex(),
            qualifier: jumpBackIn
                ? `${formatCount(jumpBackIn.active_campaigns)} active · ${formatCount(jumpBackIn.draft_campaigns)} draft`
                : null,
        },
        {
            title: 'Templates',
            icon: FileText,
            href: slug ? templatesIndex(slug) : teamsIndex(),
            qualifier: jumpBackIn
                ? `${formatCount(jumpBackIn.templates)} ${plural(jumpBackIn.templates, 'template', 'templates')} · ${formatCount(jumpBackIn.template_folders)} ${plural(jumpBackIn.template_folders, 'folder', 'folders')}`
                : null,
        },
        {
            title: 'Connections',
            icon: Plug,
            href: slug ? connectionsIndex(slug) : teamsIndex(),
            qualifier: jumpBackIn
                ? [
                      `${formatCount(jumpBackIn.connections)} ${plural(jumpBackIn.connections, 'inbox', 'inboxes')}`,
                      jumpBackIn.connections_needing_attention > 0
                          ? `${formatCount(jumpBackIn.connections_needing_attention)} ${plural(jumpBackIn.connections_needing_attention, 'needs', 'need')} attention`
                          : null,
                  ]
                      .filter(Boolean)
                      .join(' · ')
                : null,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex w-full flex-col gap-4 px-4 py-6 lg:px-6">
                <PageHead
                    title={firstName ? `${greeting}, ${firstName}` : greeting}
                    description={
                        currentTeam
                            ? `${currentTeam.name} · ${dateFormatter.format(new Date())}`
                            : undefined
                    }
                    mobileNav
                    actions={
                        can?.create && currentTeam ? (
                            <CreateCampaignModal
                                mailerConnections={mailerConnections}
                            >
                                <Button data-test="dashboard-new-campaign">
                                    <Plus /> New campaign
                                </Button>
                            </CreateCampaignModal>
                        ) : null
                    }
                />

                {invitationCount > 0 ? (
                    <TeamInvitationAlert
                        message={`You have ${invitationCount} pending ${plural(
                            invitationCount,
                            'team invitation',
                            'team invitations',
                        )} from ${invitationNames}`}
                        actionSlot={
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setShowInvitations(true)}
                            >
                                Review
                            </Button>
                        }
                    />
                ) : null}

                {metrics ? (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Emails sent · 30d"
                            value={formatCount(metrics.emails_sent)}
                            delta={
                                metrics.emails_sent_delta === null
                                    ? null
                                    : {
                                          value: metrics.emails_sent_delta,
                                          unit: '%',
                                      }
                            }
                        />
                        <StatCard
                            label="Open rate"
                            value={formatRate(metrics.open_rate)}
                            delta={
                                metrics.open_rate_delta === null
                                    ? null
                                    : {
                                          value: metrics.open_rate_delta,
                                          unit: 'pt',
                                      }
                            }
                        />
                        <StatCard
                            label="Reply rate"
                            value={formatRate(metrics.reply_rate)}
                            delta={
                                metrics.reply_rate_delta === null
                                    ? null
                                    : {
                                          value: metrics.reply_rate_delta,
                                          unit: 'pt',
                                      }
                            }
                        />
                        <StatCard
                            label="Active campaigns"
                            value={formatCount(metrics.active_campaigns)}
                        />
                    </div>
                ) : null}

                <div
                    className={cn(
                        'grid items-start gap-4',
                        setup
                            ? 'lg:grid-cols-[minmax(0,14fr)_minmax(0,11fr)]'
                            : '',
                    )}
                >
                    {setup ? (
                        <section className="rounded-lg border bg-card pb-2">
                            <div className="px-5 pt-5">
                                <div className="flex items-baseline justify-between gap-4">
                                    <h2 className="text-[16px] leading-6 font-semibold">
                                        Finish setting up
                                    </h2>
                                    <p className="text-[13px] leading-5 text-muted-foreground">
                                        {doneCount} of {checklist.length} done
                                    </p>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label="Setup progress"
                                    aria-valuemin={0}
                                    aria-valuemax={checklist.length}
                                    aria-valuenow={doneCount}
                                    className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${(doneCount / checklist.length) * 100}%`,
                                        }}
                                    />
                                </div>
                            </div>
                            <ul className="mt-3">
                                {checklist.map((step) => (
                                    <li
                                        key={step.key}
                                        className="flex items-center gap-3 border-b px-5 py-2.5"
                                    >
                                        {step.done ? (
                                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary">
                                                <Check
                                                    className="size-3 text-primary-foreground"
                                                    strokeWidth={3}
                                                />
                                            </span>
                                        ) : (
                                            <span
                                                aria-hidden="true"
                                                className="size-5 shrink-0 rounded-full border border-input"
                                            />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p
                                                className={cn(
                                                    'text-[13px] leading-5 font-medium',
                                                    step.done &&
                                                        'text-muted-foreground line-through',
                                                )}
                                            >
                                                {step.title}
                                            </p>
                                            <p className="text-[13px] leading-5 text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                        {!step.done ? (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={step.href}>
                                                    {step.cta}
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}

                    <section className="rounded-lg border bg-card pb-2">
                        <h2 className="px-5 pt-5 text-[16px] leading-6 font-semibold">
                            Jump back in
                        </h2>
                        <ul className="mt-3">
                            {destinations.map(
                                ({ title, icon: Icon, href, qualifier }) => (
                                    <li key={title}>
                                        <Link
                                            href={href}
                                            className="flex items-center gap-3 border-b px-5 py-3 transition-colors outline-none hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring-halo"
                                        >
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent">
                                                <Icon className="size-4 text-primary" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-[13px] leading-5 font-semibold">
                                                    {title}
                                                </span>
                                                {qualifier ? (
                                                    <span className="block text-[12px] leading-4 text-muted-foreground">
                                                        {qualifier}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                        </Link>
                                    </li>
                                ),
                            )}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
    showHeader: false,
});

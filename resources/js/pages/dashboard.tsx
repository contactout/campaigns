import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Circle,
    Plug,
    Send,
    Users,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as connectionsIndex } from '@/routes/mailer-connections';
import { index as teamsIndex } from '@/routes/teams';
import type { DashboardInvitation, DashboardSetup } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    setup?: DashboardSetup | null;
};

export default function Dashboard({
    pendingInvitations = [],
    setup = null,
}: Props) {
    const { currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const setupComplete =
        setup !== null &&
        setup.has_connection &&
        setup.has_contact &&
        setup.has_campaign;

    const showChecklist =
        currentTeam !== null && setup !== null && !setupComplete;

    const checklist = currentTeam
        ? [
              {
                  key: 'connection',
                  done: setup?.has_connection ?? false,
                  title: 'Connect an inbox',
                  description:
                      'Link Gmail, Outlook, or SMTP so you can send campaigns.',
                  href: connectionsIndex(currentTeam.slug),
                  cta: 'Go to Connections',
              },
              {
                  key: 'contact',
                  done: setup?.has_contact ?? false,
                  title: 'Add contacts',
                  description:
                      'Build your audience before you launch a sequence.',
                  href: contactsIndex(currentTeam.slug),
                  cta: 'Go to Contacts',
              },
              {
                  key: 'campaign',
                  done: setup?.has_campaign ?? false,
                  title: 'Create a campaign',
                  description:
                      'Write your first email sequence and add recipients.',
                  href: campaignsIndex(currentTeam.slug),
                  cta: 'Go to Campaigns',
              },
          ]
        : [];

    const nextStep = checklist.find((step) => !step.done);

    const destinations = [
        {
            title: 'Contacts',
            description:
                'Organize people and lists for your next conversation.',
            icon: Users,
            href: currentTeam ? contactsIndex(currentTeam.slug) : teamsIndex(),
        },
        {
            title: 'Campaigns',
            description: 'Create and manage your email sequences.',
            icon: Send,
            href: currentTeam ? campaignsIndex(currentTeam.slug) : teamsIndex(),
        },
        {
            title: 'Teams',
            description: 'Work together and manage your team.',
            icon: UsersRound,
            href: teamsIndex(),
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-8 sm:px-6 sm:py-12">
                {showChecklist ? (
                    <div className="rounded-2xl border bg-gradient-to-br from-muted/70 to-background p-6 sm:p-10">
                        <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            Get started
                        </p>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                            Set up {currentTeam?.name}
                        </h1>
                        <p className="mt-3 max-w-xl text-muted-foreground">
                            Connect an inbox, add contacts, then create your
                            first campaign.
                        </p>

                        <ol className="mt-8 space-y-3">
                            {checklist.map((step, index) => (
                                <li
                                    key={step.key}
                                    className="flex items-start gap-3 rounded-xl border bg-card/70 p-4"
                                >
                                    {step.done ? (
                                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <Circle className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">
                                            <span className="text-muted-foreground">
                                                {index + 1}.
                                            </span>{' '}
                                            {step.title}
                                        </p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
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
                        </ol>

                        {nextStep ? (
                            <Button className="mt-6" asChild>
                                <Link href={nextStep.href}>
                                    {nextStep.cta} <ArrowRight />
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <div className="rounded-2xl border bg-gradient-to-br from-muted/70 to-background p-6 sm:p-10">
                        <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            Workspace
                        </p>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                            Welcome
                            {currentTeam ? ` to ${currentTeam.name}` : ' back'}.
                        </h1>
                        <p className="mt-3 max-w-xl text-muted-foreground">
                            Everything you need to organize contacts, build
                            campaigns, and work with your team.
                        </p>
                        {currentTeam && (
                            <Button className="mt-6" asChild>
                                <Link href={contactsIndex(currentTeam.slug)}>
                                    Explore contacts <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                )}

                <section aria-labelledby="quick-links-title">
                    <div className="mb-5 flex items-baseline justify-between gap-4">
                        <h2
                            id="quick-links-title"
                            className="text-lg font-semibold"
                        >
                            Your workspace
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            Pick up where you left off
                        </span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        {destinations.map(
                            ({ title, description, icon: Icon, href }) => (
                                <Link
                                    key={title}
                                    href={href}
                                    className="group flex min-h-48 flex-col rounded-xl border bg-card p-6 transition-colors hover:border-foreground/30 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="mt-6 font-semibold">
                                        {title}
                                    </h3>
                                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                        {description}
                                    </p>
                                    <ArrowRight className="mt-auto ml-auto size-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
                                </Link>
                            ),
                        )}
                    </div>
                </section>

                {showChecklist ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Plug className="size-4" />
                        Tip: you can connect Gmail, Outlook, or SMTP from
                        Connections anytime.
                    </p>
                ) : null}
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
});

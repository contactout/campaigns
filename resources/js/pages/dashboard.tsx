import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Send, Users, UsersRound } from 'lucide-react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as teamsIndex } from '@/routes/teams';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
};

export default function Dashboard({ pendingInvitations = [] }: Props) {
    const { currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
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

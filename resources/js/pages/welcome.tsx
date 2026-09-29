import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Send, Users, Workflow } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as teamsIndex } from '@/routes/teams';

const features = [
    {
        icon: Users,
        title: 'Keep contacts organized',
        description: 'Build lists and keep every conversation in context.',
    },
    {
        icon: Workflow,
        title: 'Build your sequence',
        description: 'Plan each step of your outreach in one place.',
    },
    {
        icon: Send,
        title: 'Reach out together',
        description: 'Manage campaigns with your team from start to finish.',
    },
];

export default function Welcome() {
    const { auth, currentTeam, canRegister } = usePage().props;
    const destination = auth.user
        ? currentTeam
            ? dashboard(currentTeam.slug)
            : teamsIndex()
        : canRegister
          ? register()
          : login();

    return (
        <>
            <Head title="Welcome" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border/70">
                    <nav
                        className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
                        aria-label="Main navigation"
                    >
                        <span className="text-xl font-bold tracking-tight">
                            Campaigns
                        </span>
                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Button size="sm" asChild>
                                    <Link href={destination}>
                                        Dashboard <ArrowRight />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button size="sm" variant="ghost" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button size="sm" asChild>
                                            <Link href={register()}>
                                                Get started <ArrowRight />
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid max-w-6xl gap-12 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-16">
                        <div>
                            <p className="mb-5 inline-flex rounded-full border bg-muted/60 px-3 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Your outreach workspace
                            </p>
                            <h1 className="max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                                Make every connection count.
                            </h1>
                            <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground">
                                Bring contacts, email sequences, and your team
                                together in one calm place to do your best
                                outreach work.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Button size="lg" asChild>
                                    <Link href={destination}>
                                        {auth.user
                                            ? 'Open dashboard'
                                            : canRegister
                                              ? 'Get started'
                                              : 'Log in'}{' '}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                {!auth.user && canRegister && (
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>
                                            I have an account
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div
                            className="relative rounded-3xl border bg-muted/40 p-4 shadow-sm sm:p-7"
                            aria-hidden="true"
                        >
                            <div className="rounded-2xl border bg-card p-5 shadow-sm sm:p-7">
                                <div className="flex items-center gap-2 border-b pb-5">
                                    <span className="size-2 rounded-full bg-emerald-500" />
                                    <span className="text-sm font-medium">
                                        Outreach, organized
                                    </span>
                                </div>
                                <div className="mt-6 space-y-4">
                                    {features.map(
                                        (
                                            { icon: Icon, title, description },
                                            index,
                                        ) => (
                                            <div
                                                key={title}
                                                className="flex gap-4 rounded-xl border bg-background p-4"
                                            >
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                                    <Icon className="size-5" />
                                                </div>
                                                <div>
                                                    <span className="text-xs font-medium text-muted-foreground">
                                                        0{index + 1} / 03
                                                    </span>
                                                    <p className="mt-1 font-semibold">
                                                        {title}
                                                    </p>
                                                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                                        {description}
                                                    </p>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, MoreVertical, Plus, Search, Send } from 'lucide-react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import CreateCampaignModal from '@/components/campaigns/create-campaign-modal';
import EmptyState from '@/components/empty-state';
import PageHead from '@/components/page-head';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { cn } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/relative-time';
import { index, show } from '@/routes/campaigns';
import { index as templatesIndex } from '@/routes/templates';
import type {
    CampaignSummary,
    MailerConnectionOption,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    campaigns: Paginated<CampaignSummary>;
    filters: {
        q: string | null;
        status: string | null;
    };
    statuses: StatusOption[];
    mailerConnections: MailerConnectionOption[];
    can: {
        create: boolean;
    };
};

export default function CampaignsIndex({
    campaigns,
    filters,
    statuses,
    mailerConnections,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const { search, updateSearch, submitSearch, clearSearch, cancelSearch } =
        useDebouncedSearch(index.url(slug), {
            q: filters.q,
            status: filters.status ?? '',
        });

    const navigate = (params: { q?: string; status?: string }) => {
        cancelSearch();
        router.get(
            index.url(slug),
            {
                q: params.q ?? filters.q ?? '',
                status: params.status ?? filters.status ?? '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const statusLabel =
        statuses.find((status) => status.value === filters.status)?.label ??
        null;
    const hasFilters = Boolean(filters.q || filters.status);
    const isEmpty = campaigns.data.length === 0;
    // Only a team with no campaigns at all is a first run; an out-of-range page
    // or a filter that matches nothing is not.
    const isFirstRun = campaigns.total === 0 && !hasFilters;
    const previousLink = campaigns.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = campaigns.links.find((link) =>
        link.label.includes('Next'),
    );

    const clearFilters = () => {
        clearSearch();
        router.get(
            index.url(slug),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const newCampaignButton = can.create ? (
        <CreateCampaignModal mailerConnections={mailerConnections}>
            <Button data-test="campaigns-new-button" aria-label="New campaign">
                <Plus />
                <span className="hidden lg:inline">New campaign</span>
                <span className="lg:hidden">New</span>
            </Button>
        </CreateCampaignModal>
    ) : null;

    // The 390 empty panel has room for the full label even though the head does
    // not; the board shows `New campaign` there.
    const newCampaignPanelButton = can.create ? (
        <CreateCampaignModal mailerConnections={mailerConnections}>
            <Button
                data-test="campaigns-new-button-panel"
                aria-label="New campaign"
            >
                <Plus /> New campaign
            </Button>
        </CreateCampaignModal>
    ) : null;

    return (
        <>
            <Head title="Campaigns" />

            <div
                className={cn(
                    'flex w-full flex-col px-4 py-6 lg:px-6',
                    isFirstRun ? 'gap-3' : 'gap-5',
                )}
            >
                <PageHead
                    title="Campaigns"
                    description="Sequences you send from your connected inboxes"
                    hideDescriptionOnMobile
                    mobileNav
                    actions={isFirstRun ? null : newCampaignButton}
                />

                {isFirstRun ? null : (
                    <form
                        role="search"
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <div className="relative min-w-0 flex-1 lg:w-[260px] lg:flex-none">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search campaigns"
                                value={search}
                                onChange={(event) =>
                                    updateSearch(event.target.value)
                                }
                                placeholder="Search campaigns…"
                                className="h-8 pl-8"
                            />
                        </div>

                        <Select
                            value={filters.status ?? 'all'}
                            onValueChange={(value) =>
                                navigate({
                                    q: search,
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Filter campaigns by status"
                                className="w-[101px] shrink-0 lg:w-[150px]"
                            >
                                <span className="truncate lg:hidden">
                                    {statusLabel ?? 'All'}
                                </span>
                                <span className="hidden truncate lg:block">
                                    {statusLabel ?? 'All statuses'}
                                </span>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="ms-auto hidden text-body leading-5 text-muted-foreground lg:block">
                            {campaigns.total}{' '}
                            {campaigns.total === 1 ? 'campaign' : 'campaigns'}
                        </p>
                    </form>
                )}

                {isFirstRun ? (
                    <EmptyState
                        icon={Send}
                        title="No campaigns yet"
                        description="Write a short email sequence, add recipients from Contacts, and send from your connected inbox."
                        actions={
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={templatesIndex.url(slug)}>
                                        <FileText /> Browse templates
                                    </Link>
                                </Button>
                                {newCampaignPanelButton}
                            </>
                        }
                    />
                ) : (
                    <div className="overflow-hidden border-y bg-card max-lg:-mx-4 lg:rounded-lg lg:border">
                        {isEmpty ? (
                            hasFilters ? (
                                <EmptyState
                                    className="border-0 py-16"
                                    title="No campaigns match your filters"
                                    description="Try a different search term or status."
                                    actions={
                                        <Button
                                            variant="outline"
                                            onClick={clearFilters}
                                        >
                                            Clear filters
                                        </Button>
                                    }
                                />
                            ) : (
                                <EmptyState
                                    className="border-0 py-16"
                                    title="This page has no campaigns"
                                    description="The list has fewer pages than this one. Go back to the first page."
                                    actions={
                                        <Button variant="outline" asChild>
                                            <Link href={index.url(slug)}>
                                                Back to page 1
                                            </Link>
                                        </Button>
                                    }
                                />
                            )
                        ) : (
                            <>
                                <ul
                                    className="divide-y lg:hidden"
                                    aria-label="Campaigns"
                                    data-test="campaign-list-mobile"
                                >
                                    {campaigns.data.map((campaign) => {
                                        const created = formatRelativeTime(
                                            campaign.created_at,
                                        );
                                        const meta = [
                                            `${campaign.steps_count} ${campaign.steps_count === 1 ? 'step' : 'steps'}`,
                                            `${campaign.recipients_count} ${campaign.recipients_count === 1 ? 'recipient' : 'recipients'}`,
                                            created
                                                ? `Created ${created}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ');

                                        return (
                                            <li key={campaign.id}>
                                                <Link
                                                    href={show.url([
                                                        slug,
                                                        campaign.id,
                                                    ])}
                                                    data-test="campaign-row-mobile"
                                                    className="flex min-h-[68px] items-center gap-3 px-4 py-3 outline-none hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-primary-border focus-visible:ring-inset"
                                                >
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate text-body leading-5 font-semibold">
                                                            {campaign.name}
                                                        </span>
                                                        <span className="mt-0.5 block truncate text-label leading-4 text-muted-foreground">
                                                            {meta}
                                                        </span>
                                                    </span>
                                                    <CampaignStatusBadge
                                                        status={campaign.status}
                                                        label={
                                                            campaign.status_label
                                                        }
                                                    />
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <div className="hidden lg:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead>Name</TableHead>
                                                <TableHead className="w-[120px]">
                                                    Status
                                                </TableHead>
                                                <TableHead className="w-[80px]">
                                                    Steps
                                                </TableHead>
                                                <TableHead className="w-[116px]">
                                                    Recipients
                                                </TableHead>
                                                <TableHead className="w-[64px]">
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {campaigns.data.map((campaign) => {
                                                const created =
                                                    formatRelativeTime(
                                                        campaign.created_at,
                                                    );

                                                return (
                                                    <TableRow
                                                        key={campaign.id}
                                                        data-test="campaign-row"
                                                    >
                                                        <TableCell className="py-[3.5px]">
                                                            <Link
                                                                href={show.url([
                                                                    slug,
                                                                    campaign.id,
                                                                ])}
                                                                className="block truncate font-semibold hover:underline"
                                                            >
                                                                {campaign.name}
                                                            </Link>
                                                            {created ? (
                                                                <span className="block text-label leading-4 text-muted-foreground">
                                                                    Created{' '}
                                                                    {created}
                                                                </span>
                                                            ) : null}
                                                        </TableCell>
                                                        <TableCell>
                                                            <CampaignStatusBadge
                                                                status={
                                                                    campaign.status
                                                                }
                                                                label={
                                                                    campaign.status_label
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                campaign.steps_count
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                campaign.recipients_count
                                                            }
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7 text-muted-foreground"
                                                                        aria-label={`Actions for ${campaign.name}`}
                                                                    >
                                                                        <MoreVertical className="size-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    <DropdownMenuItem
                                                                        onSelect={() =>
                                                                            router.visit(
                                                                                show(
                                                                                    [
                                                                                        slug,
                                                                                        campaign.id,
                                                                                    ],
                                                                                ),
                                                                            )
                                                                        }
                                                                    >
                                                                        Open
                                                                        campaign
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="flex h-[52px] items-center justify-between gap-2 border-t px-4">
                                    <p className="text-body leading-5 text-muted-foreground">
                                        Showing {campaigns.from ?? 0}–
                                        {campaigns.to ?? 0} of {campaigns.total}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        {previousLink?.url ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={previousLink.url}
                                                    preserveScroll
                                                >
                                                    Previous
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled
                                            >
                                                Previous
                                            </Button>
                                        )}
                                        {nextLink?.url ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={nextLink.url}
                                                    preserveScroll
                                                >
                                                    Next
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled
                                            >
                                                Next
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

CampaignsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
    showHeader: false,
});

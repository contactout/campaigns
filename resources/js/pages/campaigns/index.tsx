import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, MoreVertical, Plus, Search, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    const [search, setSearch] = useState(filters.q ?? '');

    // The server owns `q`; keep the input in step when a filter change reloads it.
    useEffect(() => {
        setSearch(filters.q ?? '');
    }, [filters.q]);

    const navigate = (params: { q?: string; status?: string }) => {
        router.get(
            index.url(slug),
            {
                q: params.q ?? filters.q ?? '',
                status: params.status ?? filters.status ?? '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

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
        setSearch('');
        router.get(
            index.url(slug),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const newCampaignButton = can.create ? (
        <CreateCampaignModal mailerConnections={mailerConnections}>
            <Button data-test="campaigns-new-button">
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
                    mobileNav
                    actions={isFirstRun ? null : newCampaignButton}
                />

                {isFirstRun ? null : (
                    <form
                        role="search"
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            navigate({ q: search });
                        }}
                    >
                        <div className="relative w-full sm:w-[260px]">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search campaigns"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
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
                                className="w-[150px]"
                            >
                                <SelectValue placeholder="All statuses" />
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

                        <p className="ms-auto text-body text-muted-foreground">
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
                                {newCampaignButton}
                            </>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card">
                        {isEmpty ? (
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
                            <>
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
                                            const created = formatRelativeTime(
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
                                                            <span className="block text-label text-muted-foreground">
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
                                                        {campaign.steps_count}
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

                                <div className="flex h-[52px] items-center justify-between gap-2 border-t px-4">
                                    <p className="text-body text-muted-foreground">
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

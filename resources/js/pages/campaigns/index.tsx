import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, Send } from 'lucide-react';
import { useState } from 'react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import CreateCampaignModal from '@/components/campaigns/create-campaign-modal';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/campaigns';
import { index as connectionsIndex } from '@/routes/mailer-connections';
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

    return (
        <>
            <Head title="Campaigns" />

            <h1 className="sr-only">Campaigns</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Campaigns"
                        description="Email sequences sent to your contacts"
                    />

                    {can.create ? (
                        <CreateCampaignModal
                            mailerConnections={mailerConnections}
                        >
                            <Button data-test="campaigns-new-button">
                                <Plus /> New campaign
                            </Button>
                        </CreateCampaignModal>
                    ) : null}
                </div>

                <form
                    role="search"
                    className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3 sm:p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate({ q: search });
                    }}
                >
                    <div className="relative min-w-48 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label="Search campaigns"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search campaigns"
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            navigate({ status: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter campaigns by status"
                            className="w-44 sm:w-48"
                        >
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
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

                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {campaigns.data.length === 0 ? (
                    <EmptyState
                        icon={Send}
                        title={
                            filters.q || filters.status
                                ? 'No matching campaigns'
                                : 'No campaigns yet'
                        }
                        description={
                            filters.q || filters.status
                                ? 'Try another search or change your filters.'
                                : mailerConnections.length === 0
                                  ? 'Connect an inbox first, then create your first sequence.'
                                  : 'Create your first sequence to start reaching out.'
                        }
                        actions={
                            !filters.q && !filters.status ? (
                                <>
                                    {mailerConnections.length === 0 ? (
                                        <Button variant="secondary" asChild>
                                            <Link href={connectionsIndex(slug)}>
                                                Go to Connections
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {can.create ? (
                                        <CreateCampaignModal
                                            mailerConnections={
                                                mailerConnections
                                            }
                                        >
                                            <Button
                                                variant={
                                                    mailerConnections.length ===
                                                    0
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                            >
                                                <Plus /> New campaign
                                            </Button>
                                        </CreateCampaignModal>
                                    ) : null}
                                </>
                            ) : null
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Steps
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Recipients
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {campaigns.data.map((campaign) => (
                                    <tr
                                        key={campaign.id}
                                        className="border-t transition-colors hover:bg-muted/30"
                                        data-test="campaign-row"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {campaign.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <CampaignStatusBadge
                                                status={campaign.status}
                                                label={campaign.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {campaign.steps_count}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {campaign.recipients_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={show([
                                                        slug,
                                                        campaign.id,
                                                    ])}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={campaigns.links} />
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
});

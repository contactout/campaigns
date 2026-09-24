import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import CreateCampaignModal from '@/components/campaigns/create-campaign-modal';
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
import type { CampaignSummary, Paginated, StatusOption } from '@/types';

type Props = {
    campaigns: Paginated<CampaignSummary>;
    filters: {
        q: string | null;
        status: string | null;
    };
    statuses: StatusOption[];
    can: {
        create: boolean;
    };
};

export default function CampaignsIndex({
    campaigns,
    filters,
    statuses,
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

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Campaigns"
                        description="Email sequences sent to your contacts"
                    />

                    {can.create ? (
                        <CreateCampaignModal>
                            <Button data-test="campaigns-new-button">
                                <Plus /> New campaign
                            </Button>
                        </CreateCampaignModal>
                    ) : null}
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate({ q: search });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search campaigns"
                        className="max-w-xs"
                    />

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            navigate({ status: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-48">
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
                    <p className="py-8 text-center text-muted-foreground">
                        No campaigns yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
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
                                        className="border-t"
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

import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Archive,
    Copy,
    Pencil,
    Play,
    Plus,
    Square,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import DeleteCampaignModal from '@/components/campaigns/delete-campaign-modal';
import EditCampaignModal from '@/components/campaigns/edit-campaign-modal';
import StepFormModal from '@/components/campaigns/step-form-modal';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { archive, duplicate, index, start, stop } from '@/routes/campaigns';
import { destroy as destroyStep, reorder } from '@/routes/campaigns/steps';
import type {
    CampaignDetail,
    CampaignPermissions,
    CampaignStats,
    CampaignStep,
} from '@/types';

type Props = {
    campaign: CampaignDetail;
    steps: CampaignStep[];
    stats: CampaignStats;
    can: CampaignPermissions;
};

export default function CampaignShow({ campaign, steps, stats, can }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const errors = usePage().props.errors as Record<string, string>;

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [stepModalOpen, setStepModalOpen] = useState(false);
    const [editingStep, setEditingStep] = useState<CampaignStep | null>(null);

    const canStart =
        can.start && ['Draft', 'Stopped'].includes(campaign.status);
    const canStop = can.stop && campaign.status === 'Active';
    const canArchive = can.archive && campaign.status !== 'Archived';

    const openNewStep = () => {
        setEditingStep(null);
        setStepModalOpen(true);
    };

    const openEditStep = (step: CampaignStep) => {
        setEditingStep(step);
        setStepModalOpen(true);
    };

    const moveStep = (index: number, direction: -1 | 1) => {
        const next = [...steps];
        const target = index + direction;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];

        router.post(
            reorder.url([slug, campaign.id]),
            { steps: next.map((step) => step.id) },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={campaign.name} />

            <h1 className="sr-only">{campaign.name}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Heading variant="small" title={campaign.name} />
                        <CampaignStatusBadge
                            status={campaign.status}
                            label={campaign.status_label}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canStart ? (
                            <Form {...start.form([slug, campaign.id])}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="campaign-start-button"
                                    >
                                        <Play /> Start
                                    </Button>
                                )}
                            </Form>
                        ) : null}

                        {canStop ? (
                            <Form {...stop.form([slug, campaign.id])}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Square /> Stop
                                    </Button>
                                )}
                            </Form>
                        ) : null}

                        {can.duplicate ? (
                            <Form {...duplicate.form([slug, campaign.id])}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Copy /> Duplicate
                                    </Button>
                                )}
                            </Form>
                        ) : null}

                        {canArchive ? (
                            <Form {...archive.form([slug, campaign.id])}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Archive /> Archive
                                    </Button>
                                )}
                            </Form>
                        ) : null}

                        {can.update ? (
                            <Button
                                variant="secondary"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil /> Edit
                            </Button>
                        ) : null}

                        {can.delete ? (
                            <Button
                                variant="destructive"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2 /> Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                {errors.campaign ? (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.campaign}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Steps" value={stats.steps_count} />
                    <StatCard
                        label="Recipients"
                        value={stats.recipients_count}
                    />
                    <StatCard label="Timezone" value={campaign.timezone} />
                </div>

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Steps</h2>
                    {can.update ? (
                        <Button
                            variant="secondary"
                            onClick={openNewStep}
                            data-test="campaign-add-step-button"
                        >
                            <Plus /> Add step
                        </Button>
                    ) : null}
                </div>

                {steps.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        No steps yet. Add your first email step.
                    </p>
                ) : (
                    <div className="flex flex-col gap-3">
                        {steps.map((step, index) => (
                            <Card key={step.id} data-test="campaign-step-row">
                                <CardContent className="flex items-start justify-between gap-4">
                                    <div className="flex gap-4">
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                                            {step.sequence}
                                        </span>
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {step.subject || '(no subject)'}
                                            </p>
                                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                                {step.body}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Day {step.day ?? 0}
                                                {step.time
                                                    ? ` at ${step.time.slice(0, 5)}`
                                                    : ''}
                                                {step.is_threaded
                                                    ? ' · threaded'
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-1">
                                        {can.update ? (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={index === 0}
                                                    onClick={() =>
                                                        moveStep(index, -1)
                                                    }
                                                >
                                                    <ArrowUp className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={
                                                        index ===
                                                        steps.length - 1
                                                    }
                                                    onClick={() =>
                                                        moveStep(index, 1)
                                                    }
                                                >
                                                    <ArrowDown className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditStep(step)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Form
                                                    {...destroyStep.form([
                                                        slug,
                                                        campaign.id,
                                                        step.id,
                                                    ])}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </Form>
                                            </>
                                        ) : null}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={index(slug)}>Back to campaigns</Link>
                    </Button>
                </div>
            </div>

            <EditCampaignModal
                campaign={campaign}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteCampaignModal
                campaign={campaign}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <StepFormModal
                campaignId={campaign.id}
                step={editingStep}
                open={stepModalOpen}
                onOpenChange={setStepModalOpen}
            />
        </>
    );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardContent className="grid gap-1">
                <span className="text-xs tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-2xl font-semibold">{value}</span>
            </CardContent>
        </Card>
    );
}

CampaignShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

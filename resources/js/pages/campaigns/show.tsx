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
import AddRecipientsModal from '@/components/campaigns/add-recipients-modal';
import DeleteCampaignModal from '@/components/campaigns/delete-campaign-modal';
import EditCampaignModal from '@/components/campaigns/edit-campaign-modal';
import StepFormModal from '@/components/campaigns/step-form-modal';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { archive, duplicate, index, start, stop } from '@/routes/campaigns';
import { destroy as destroyRecipient } from '@/routes/campaigns/recipients';
import { destroy as destroyStep, reorder } from '@/routes/campaigns/steps';
import type {
    AvailableContact,
    CampaignDetail,
    CampaignListOption,
    CampaignPermissions,
    CampaignSignatureOption,
    CampaignStats,
    CampaignStep,
    CampaignTemplateOption,
    MailerConnectionOption,
    MergePlaceholder,
    Paginated,
    RecipientSummary,
} from '@/types';

type Props = {
    campaign: CampaignDetail;
    steps: CampaignStep[];
    stats: CampaignStats;
    recipients: Paginated<RecipientSummary>;
    availableContacts: AvailableContact[];
    lists: CampaignListOption[];
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    mailerConnections: MailerConnectionOption[];
    can: CampaignPermissions;
};

export default function CampaignShow({
    campaign,
    steps,
    stats,
    recipients,
    availableContacts,
    lists,
    templates,
    signatures,
    placeholders,
    mailerConnections,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const errors = usePage().props.errors as Record<string, string>;

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [stepModalOpen, setStepModalOpen] = useState(false);
    const [recipientsOpen, setRecipientsOpen] = useState(false);
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

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Steps" value={stats.steps_count} />
                    <StatCard
                        label="Recipients"
                        value={stats.recipients_count}
                    />
                    <StatCard label="Sent" value={stats.emails_sent} />
                    <StatCard label="Failed" value={stats.emails_failed} />
                    <StatCard label="Opened" value={stats.opened} />
                    <StatCard label="Clicked" value={stats.clicked} />
                    <StatCard label="Unsubscribed" value={stats.unsubscribed} />
                    <StatCard label="Timezone" value={campaign.timezone} />
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Recipients</h2>
                        {can.update ? (
                            <Button
                                variant="secondary"
                                onClick={() => setRecipientsOpen(true)}
                                data-test="campaign-add-recipients-button"
                            >
                                <Plus /> Add recipients
                            </Button>
                        ) : null}
                    </div>

                    {recipients.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed px-6 py-12 text-center">
                            <h3 className="font-semibold">No recipients yet</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add contacts or lists to start this campaign.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Name
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Email
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {recipients.data.map((recipient) => (
                                        <tr
                                            key={recipient.id}
                                            className="border-t"
                                            data-test="campaign-recipient-row"
                                        >
                                            <td className="px-4 py-2 font-medium">
                                                {recipient.name}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {recipient.email}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant="secondary">
                                                    {recipient.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {can.update ? (
                                                    <Form
                                                        {...destroyRecipient.form(
                                                            [
                                                                slug,
                                                                campaign.id,
                                                                recipient.id,
                                                            ],
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Remove
                                                            </Button>
                                                        )}
                                                    </Form>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination links={recipients.links} />
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
                    <div className="rounded-xl border border-dashed px-6 py-12 text-center">
                        <h3 className="font-semibold">No steps yet</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add your first email step to build this sequence.
                        </p>
                        {can.update && (
                            <Button
                                variant="outline"
                                className="mt-5"
                                onClick={openNewStep}
                            >
                                <Plus /> Add step
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {steps.map((step, index) => (
                            <Card key={step.id} data-test="campaign-step-row">
                                <CardContent className="flex flex-wrap items-start justify-between gap-4 sm:flex-nowrap">
                                    <div className="flex min-w-0 gap-4">
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                                            {step.sequence}
                                        </span>
                                        <div className="min-w-0 space-y-1">
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

                                    <div className="flex items-center gap-1 sm:shrink-0">
                                        {can.update ? (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={index === 0}
                                                    onClick={() =>
                                                        moveStep(index, -1)
                                                    }
                                                    aria-label={`Move step ${step.sequence} up`}
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
                                                    aria-label={`Move step ${step.sequence} down`}
                                                >
                                                    <ArrowDown className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditStep(step)
                                                    }
                                                    aria-label={`Edit step ${step.sequence}`}
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
                                                            aria-label={`Delete step ${step.sequence}`}
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
                mailerConnections={mailerConnections}
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
                templates={templates}
                signatures={signatures}
                placeholders={placeholders}
                open={stepModalOpen}
                onOpenChange={setStepModalOpen}
            />
            <AddRecipientsModal
                campaignId={campaign.id}
                availableContacts={availableContacts}
                lists={lists}
                open={recipientsOpen}
                onOpenChange={setRecipientsOpen}
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

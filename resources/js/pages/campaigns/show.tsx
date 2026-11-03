import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Archive,
    Copy,
    MoreHorizontal,
    Pencil,
    Play,
    Plus,
    Square,
    Trash2,
} from 'lucide-react';
import { type KeyboardEvent, useEffect, useRef, useState } from 'react';
import AddRecipientsModal from '@/components/campaigns/add-recipients-modal';
import CampaignRecipientsTab from '@/components/campaigns/campaign-recipients-tab';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import CampaignStepsTab from '@/components/campaigns/campaign-steps-tab';
import ConnectionWarning from '@/components/campaigns/connection-warning';
import DeleteCampaignModal from '@/components/campaigns/delete-campaign-modal';
import EditCampaignModal from '@/components/campaigns/edit-campaign-modal';
import SequenceEditor from '@/components/campaigns/sequence-editor';
import ConfirmDialog from '@/components/confirm-dialog';
import PageHead from '@/components/page-head';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import {
    archive,
    duplicate,
    index,
    start as startCampaign,
    stop,
} from '@/routes/campaigns';
import type {
    AvailableContact,
    CampaignConnection,
    CampaignDetail,
    CampaignListOption,
    CampaignPermissions,
    CampaignSignatureOption,
    CampaignStartState,
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
    connection: CampaignConnection | null;
    steps: CampaignStep[];
    stats: CampaignStats;
    start: CampaignStartState;
    recipients: Paginated<RecipientSummary>;
    recipientsSearch: string | null;
    availableContacts: AvailableContact[];
    lists: CampaignListOption[];
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    mailerConnections: MailerConnectionOption[];
    can: CampaignPermissions;
};

type Tab = 'steps' | 'recipients';

const tabs: Array<{ key: Tab; label: string }> = [
    { key: 'steps', label: 'Steps' },
    { key: 'recipients', label: 'Recipients' },
];

export default function CampaignShow({
    campaign,
    connection,
    steps,
    stats,
    start,
    recipients,
    recipientsSearch,
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

    // A Recipients URL must open on Recipients: Back, a reload or a shared
    // `?recipients_q=…` link all remount the page with that query string.
    const [tab, setTab] = useState<Tab>(() =>
        recipientsSearch || recipients.current_page > 1
            ? 'recipients'
            : 'steps',
    );
    const [editor, setEditor] = useState<{
        stepId: number | null;
        opener: 'row' | 'add';
        sequence?: number;
    } | null>(null);
    const [pendingTab, setPendingTab] = useState<Tab | null>(null);
    const editorDirty = useRef(false);
    const openerSelector = useRef<string | null>(null);
    const campaignId = useRef(campaign.id);
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [recipientsOpen, setRecipientsOpen] = useState(false);
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);

    const canStart =
        can.start && ['Draft', 'Stopped'].includes(campaign.status);
    // The server only reports reasons for Draft/Stopped; a member without
    // can.start still has no Start action to explain.
    const startBlocked = canStart && start.blocked;
    const blockingReasonCount = start.reasons.filter(
        (reason) => reason.blocking,
    ).length;
    const canStop = can.stop && campaign.status === 'Active';
    const canArchive = can.archive && campaign.status !== 'Archived';

    // Duplicate redirects to a copy of the campaign in the same component, so
    // the tab and the open editor have to be reset when the campaign changes.
    useEffect(() => {
        if (campaignId.current === campaign.id) {
            return;
        }

        campaignId.current = campaign.id;
        setEditor(null);
        setPendingTab(null);
        editorDirty.current = false;
        setTab(
            recipientsSearch || recipients.current_page > 1
                ? 'recipients'
                : 'steps',
        );
    }, [campaign.id, recipientsSearch, recipients.current_page]);

    const applyTab = (next: Tab) => {
        setTab(next);
        setEditor(null);
        editorDirty.current = false;
    };

    const selectTab = (next: Tab) => {
        if (next === tab) {
            return;
        }

        if (editor !== null && editorDirty.current) {
            setPendingTab(next);

            return;
        }

        applyTab(next);
    };

    // Return focus to whatever opened the editor; the trigger unmounts with it.
    useEffect(() => {
        if (editor !== null || openerSelector.current === null) {
            return;
        }

        const selector = openerSelector.current;
        openerSelector.current = null;

        requestAnimationFrame(() => {
            // The 390 list and the desktop list each render an Add step button,
            // and only one of them is displayed at a time.
            const targets = document.querySelectorAll<HTMLElement>(selector);
            const visible = [...targets].find(
                (target) => target.offsetParent !== null,
            );

            visible?.focus();
        });
    }, [editor]);

    const openEditor = (next: {
        stepId: number | null;
        opener: 'row' | 'add';
        sequence?: number;
    }) => {
        setEditor(next);
    };

    const closeEditor = () => {
        if (editor !== null) {
            openerSelector.current =
                editor.opener === 'add'
                    ? '[data-test="campaign-add-step-button"]'
                    : `[data-test="campaign-step-row"] button[aria-label="Actions for step ${editor.sequence ?? ''}"]`;
        }

        setEditor(null);
        editorDirty.current = false;
    };

    const onTabKeyDown = (
        event: KeyboardEvent<HTMLButtonElement>,
        from: Tab,
    ) => {
        const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];

        if (!keys.includes(event.key)) {
            return;
        }

        event.preventDefault();

        const current = tabs.findIndex((item) => item.key === from);
        const next =
            event.key === 'ArrowLeft'
                ? (current - 1 + tabs.length) % tabs.length
                : event.key === 'ArrowRight'
                  ? (current + 1) % tabs.length
                  : event.key === 'Home'
                    ? 0
                    : tabs.length - 1;

        selectTab(tabs[next].key);
        tabRefs.current[next]?.focus();
    };

    const openStep = (step: CampaignStep) =>
        openEditor({
            stepId: step.id,
            opener: 'row',
            sequence: step.sequence,
        });

    const actions = (
        <>
            {can.update ? (
                <Button variant="outline" onClick={() => setEditOpen(true)}>
                    <Pencil /> Edit
                </Button>
            ) : null}

            {can.duplicate ? (
                <div className="hidden lg:block">
                    <Form {...duplicate.form([slug, campaign.id])}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                <Copy /> Duplicate
                            </Button>
                        )}
                    </Form>
                </div>
            ) : null}

            {canArchive ? (
                <div className="hidden lg:block">
                    <Form {...archive.form([slug, campaign.id])}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                <Archive /> Archive
                            </Button>
                        )}
                    </Form>
                </div>
            ) : null}

            {canStop ? (
                <Form {...stop.form([slug, campaign.id])}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            <Square /> Stop
                        </Button>
                    )}
                </Form>
            ) : null}

            {canStart ? (
                <Form {...startCampaign.form([slug, campaign.id])}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-disabled={startBlocked || undefined}
                            aria-describedby={
                                startBlocked ? 'start-blocked' : undefined
                            }
                            onClick={
                                startBlocked
                                    ? (event) => event.preventDefault()
                                    : undefined
                            }
                            className={startBlocked ? 'opacity-50' : undefined}
                            data-test="campaign-start-button"
                        >
                            <Play /> Start
                        </Button>
                    )}
                </Form>
            ) : null}

            {can.update ? (
                <Button
                    variant={canStart ? 'outline' : 'default'}
                    onClick={() => setRecipientsOpen(true)}
                    aria-label="Add recipients"
                    data-test="campaign-add-recipients-button"
                    className={cn(tab === 'recipients' && 'lg:hidden')}
                >
                    <Plus />
                    <span className="hidden lg:inline">Add recipients</span>
                    <span className="lg:hidden">Recipients</span>
                </Button>
            ) : null}

            {/* The 390 head has one overflow menu; the desktop keeps the explicit
                buttons above and only puts Delete behind it. */}
            {can.duplicate || canArchive || can.delete ? (
                <div className="lg:hidden">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="More campaign actions"
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="lg:hidden">
                            {can.duplicate ? (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            duplicate.url([slug, campaign.id]),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Copy /> Duplicate
                                </DropdownMenuItem>
                            ) : null}
                            {canArchive ? (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            archive.url([slug, campaign.id]),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Archive /> Archive
                                </DropdownMenuItem>
                            ) : null}
                            {can.delete ? (
                                <>
                                    <DropdownMenuSeparator className="lg:hidden" />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => setDeleteOpen(true)}
                                    >
                                        <Trash2 /> Delete campaign
                                    </DropdownMenuItem>
                                </>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ) : null}

            {can.delete ? (
                <div className="hidden lg:block">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="More campaign actions"
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setDeleteOpen(true)}
                            >
                                <Trash2 /> Delete campaign
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ) : null}
        </>
    );

    return (
        <>
            <Head title={campaign.name} />

            <div className="flex w-full flex-col gap-4 px-4 py-6 lg:px-6">
                <PageHead
                    title={campaign.name}
                    mobileDescription={`${campaign.status_label} · ${stats.recipients_count} ${
                        stats.recipients_count === 1
                            ? 'recipient'
                            : 'recipients'
                    }`}
                    back={{
                        href: index.url(slug),
                        label: 'Back to campaigns',
                    }}
                    titleAddon={
                        <span className="hidden lg:inline-flex">
                            <CampaignStatusBadge
                                status={campaign.status}
                                label={campaign.status_label}
                            />
                        </span>
                    }
                    actions={actions}
                    mobileNav
                />

                {startBlocked ? (
                    <p
                        id="start-blocked"
                        className={cn(
                            'flex items-center gap-2 rounded-md border border-destructive-foreground/30 bg-badge-danger px-3 py-2 text-body leading-5 text-badge-danger-foreground',
                            tab === 'steps' && editor === null && 'sr-only',
                        )}
                    >
                        <AlertCircle
                            className="size-4 shrink-0 text-badge-danger-foreground"
                            aria-hidden
                        />
                        This campaign can&apos;t start until you resolve{' '}
                        {blockingReasonCount === 1
                            ? '1 blocking issue'
                            : `${blockingReasonCount} blocking issues`}
                        .
                    </p>
                ) : null}

                <div
                    role="tablist"
                    aria-label="Campaign sections"
                    className="flex gap-6 border-b"
                >
                    {tabs.map((item, index) => {
                        const active = tab === item.key;

                        return (
                            <button
                                key={item.key}
                                ref={(element) => {
                                    tabRefs.current[index] = element;
                                }}
                                type="button"
                                role="tab"
                                id={`campaign-tab-${item.key}`}
                                aria-selected={active}
                                aria-controls={`campaign-panel-${item.key}`}
                                tabIndex={active ? 0 : -1}
                                onClick={() => selectTab(item.key)}
                                onKeyDown={(event) =>
                                    onTabKeyDown(event, item.key)
                                }
                                className={cn(
                                    '-mb-px inline-flex items-center border-b-2 pt-1.5 pb-2 text-body leading-5 font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary-border max-lg:min-h-11',
                                    active
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {campaign.status === 'Active' ? (
                    <ConnectionWarning connection={connection} />
                ) : null}

                {errors.campaign ? (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.campaign}</AlertDescription>
                    </Alert>
                ) : null}

                {tab === 'steps' ? (
                    <div
                        key={campaign.id}
                        role="tabpanel"
                        id="campaign-panel-steps"
                        aria-labelledby="campaign-tab-steps"
                        className="flex flex-col gap-4"
                    >
                        {editor ? (
                            <SequenceEditor
                                campaignId={campaign.id}
                                campaignName={campaign.name}
                                steps={steps}
                                activeStepId={editor.stepId}
                                templates={templates}
                                signatures={signatures}
                                placeholders={placeholders}
                                canUpdate={can.update}
                                onSelectStep={openStep}
                                onAddStep={() =>
                                    openEditor({
                                        stepId: null,
                                        opener: 'add',
                                    })
                                }
                                onClose={closeEditor}
                                onDirtyChange={(isDirty) => {
                                    editorDirty.current = isDirty;
                                }}
                            />
                        ) : (
                            <CampaignStepsTab
                                campaign={campaign}
                                connection={connection}
                                steps={steps}
                                stats={stats}
                                start={start}
                                startBlocked={startBlocked}
                                canUpdate={can.update}
                                onEditStep={openStep}
                                onAddStep={() =>
                                    openEditor({
                                        stepId: null,
                                        opener: 'add',
                                    })
                                }
                            />
                        )}
                    </div>
                ) : (
                    <div
                        key={campaign.id}
                        role="tabpanel"
                        id="campaign-panel-recipients"
                        aria-labelledby="campaign-tab-recipients"
                    >
                        <CampaignRecipientsTab
                            campaignId={campaign.id}
                            recipients={recipients}
                            search={recipientsSearch}
                            canUpdate={can.update}
                            onAddRecipients={() => setRecipientsOpen(true)}
                        />
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={pendingTab !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingTab(null);
                    }
                }}
                title="Discard your changes?"
                description="This step has edits that have not been saved. Switching tabs discards them."
                confirmLabel="Discard changes"
                onConfirm={() => {
                    const next = pendingTab;
                    setPendingTab(null);

                    if (next !== null) {
                        applyTab(next);
                    }
                }}
            />

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

CampaignShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
    showHeader: false,
});

import { Form, Head, usePage } from '@inertiajs/react';
import {
    Archive,
    Copy,
    MoreHorizontal,
    Pencil,
    Play,
    Plus,
    Square,
    Trash2,
} from 'lucide-react';
import { type KeyboardEvent, useRef, useState } from 'react';
import AddRecipientsModal from '@/components/campaigns/add-recipients-modal';
import CampaignRecipientsTab from '@/components/campaigns/campaign-recipients-tab';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import CampaignStepsTab from '@/components/campaigns/campaign-steps-tab';
import DeleteCampaignModal from '@/components/campaigns/delete-campaign-modal';
import EditCampaignModal from '@/components/campaigns/edit-campaign-modal';
import SequenceEditor from '@/components/campaigns/sequence-editor';
import PageHead from '@/components/page-head';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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

    const [tab, setTab] = useState<Tab>('steps');
    const [editor, setEditor] = useState<{ stepId: number | null } | null>(
        null,
    );
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [recipientsOpen, setRecipientsOpen] = useState(false);
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);

    const canStart =
        can.start && ['Draft', 'Stopped'].includes(campaign.status);
    const canStop = can.stop && campaign.status === 'Active';
    const canArchive = can.archive && campaign.status !== 'Archived';

    const selectTab = (next: Tab) => {
        setTab(next);
        setEditor(null);
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

    const openStep = (step: CampaignStep) => setEditor({ stepId: step.id });

    const actions = (
        <>
            {can.update ? (
                <Button variant="outline" onClick={() => setEditOpen(true)}>
                    <Pencil /> Edit
                </Button>
            ) : null}

            {can.duplicate ? (
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
            ) : null}

            {canArchive ? (
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
                            disabled={processing || start.blocked}
                            aria-describedby={
                                start.blocked ? 'start-blocked' : undefined
                            }
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
                    data-test="campaign-add-recipients-button"
                >
                    <Plus /> Add recipients
                </Button>
            ) : null}

            {can.delete ? (
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
            ) : null}
        </>
    );

    return (
        <>
            <Head title={campaign.name} />

            <div className="flex w-full flex-col gap-4 px-4 py-6 lg:px-6">
                <PageHead
                    title={campaign.name}
                    back={{
                        href: index.url(slug),
                        label: 'Back to campaigns',
                    }}
                    titleAddon={
                        <CampaignStatusBadge
                            status={campaign.status}
                            label={campaign.status_label}
                        />
                    }
                    actions={actions}
                    mobileNav
                />

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
                                    '-mb-px border-b-2 pt-2.5 pb-1 text-body leading-5 font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary-border',
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

                {errors.campaign ? (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.campaign}</AlertDescription>
                    </Alert>
                ) : null}

                {tab === 'steps' ? (
                    <div
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
                                onAddStep={() => setEditor({ stepId: null })}
                                onClose={() => setEditor(null)}
                            />
                        ) : (
                            <CampaignStepsTab
                                campaign={campaign}
                                connection={connection}
                                steps={steps}
                                stats={stats}
                                start={start}
                                canUpdate={can.update}
                                onEditStep={openStep}
                                onAddStep={() => setEditor({ stepId: null })}
                            />
                        )}
                    </div>
                ) : (
                    <div
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

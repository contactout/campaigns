import { router, usePage } from '@inertiajs/react';
import {
    GripVertical,
    Mail,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import StartBlockedPanel from '@/components/campaigns/start-blocked-panel';
import StatCard from '@/components/dashboard/stat-card';
import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { timezoneLabel } from '@/lib/timezone';
import { destroy as destroyStep, reorder } from '@/routes/campaigns/steps';
import type {
    CampaignConnection,
    CampaignDetail,
    CampaignStartState,
    CampaignStats,
    CampaignStep,
} from '@/types';

type Props = {
    campaign: CampaignDetail;
    connection: CampaignConnection | null;
    steps: CampaignStep[];
    stats: CampaignStats;
    start: CampaignStartState;
    startBlocked: boolean;
    canUpdate: boolean;
    onEditStep: (step: CampaignStep) => void;
    onAddStep: () => void;
};

type DayGroup = {
    key: string;
    day: number | null;
    time: string | null;
    steps: CampaignStep[];
};

/**
 * Group consecutive steps that share a day.
 *
 * `steps` arrives ordered by `sequence`, so grouping runs instead of keying a
 * map by day keeps the order the sequence actually sends in when a campaign
 * goes back to an earlier day.
 */
function groupByDay(steps: CampaignStep[]): DayGroup[] {
    const groups: DayGroup[] = [];

    for (const step of steps) {
        const last = groups.at(-1);

        if (last && last.day === step.day) {
            last.steps.push(step);

            continue;
        }

        groups.push({
            key: `day-${step.day ?? 0}-${step.id}`,
            day: step.day,
            time: step.time,
            steps: [step],
        });
    }

    return groups;
}

const percent = (value: number | null): string =>
    value === null ? '—' : `${value}%`;

export default function CampaignStepsTab({
    campaign,
    connection,
    steps,
    stats,
    start,
    startBlocked,
    canUpdate,
    onEditStep,
    onAddStep,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [dragIndex, setDragIndex] = useState<number | null>(null);

    const groups = groupByDay(steps);
    const blockedStepIds = startBlocked
        ? start.reasons
              .filter((reason) => reason.type === 'step')
              .map((reason) => reason.step_id)
        : [];

    const openedRate =
        stats.emails_sent > 0
            ? `${Math.round((stats.opened / stats.emails_sent) * 100)}%`
            : '—';

    const sender = connection?.from_email ?? connection?.name ?? null;

    const moveStep = (index: number, direction: -1 | 1) => {
        const target = index + direction;

        if (target < 0 || target >= steps.length) {
            return;
        }

        const next = [...steps];
        [next[index], next[target]] = [next[target], next[index]];

        router.post(
            reorder.url([slug, campaign.id]),
            { steps: next.map((step) => step.id) },
            { preserveScroll: true },
        );
    };

    const dropOn = (index: number) => {
        if (dragIndex === null || dragIndex === index) {
            return;
        }

        const next = [...steps];
        const [moved] = next.splice(dragIndex, 1);
        next.splice(index, 0, moved);

        router.post(
            reorder.url([slug, campaign.id]),
            { steps: next.map((step) => step.id) },
            { preserveScroll: true },
        );

        setDragIndex(null);
    };

    return (
        <div className="flex flex-col gap-5">
            {startBlocked ? (
                <StartBlockedPanel
                    reasons={start.reasons}
                    teamSlug={slug}
                    onEditStep={(stepId) => {
                        const step = steps.find((item) => item.id === stepId);

                        if (step) {
                            onEditStep(step);
                        }
                    }}
                />
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Steps" value={String(stats.steps_count)} />
                <StatCard
                    label="Recipients"
                    value={String(stats.recipients_count)}
                />
                <StatCard label="Sent" value={String(stats.emails_sent)} />
                <StatCard label="Opened" value={openedRate} />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-body leading-5 text-muted-foreground">
                    {sender ? (
                        <>
                            Sends from {sender} ·{' '}
                            {timezoneLabel(campaign.timezone)}
                        </>
                    ) : (
                        <>
                            No sending connection ·{' '}
                            {timezoneLabel(campaign.timezone)}
                        </>
                    )}
                </p>
                {canUpdate ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onAddStep}
                        data-test="campaign-add-step-button"
                    >
                        <Plus /> Add step
                    </Button>
                ) : null}
            </div>

            {steps.length === 0 ? (
                <EmptyState
                    className="py-12"
                    icon={Mail}
                    title="No steps yet"
                    description="Add your first email step to build this sequence."
                    actions={
                        canUpdate ? (
                            <Button variant="outline" onClick={onAddStep}>
                                <Plus /> Add step
                            </Button>
                        ) : null
                    }
                />
            ) : (
                <div className="flex flex-col gap-4">
                    {groups.map((group) => (
                        <div key={group.key} className="flex flex-col gap-2.5">
                            <p className="text-body leading-5">
                                <span className="font-semibold">
                                    Day {group.day ?? 0}
                                </span>
                                <span className="mx-1.5 text-muted-foreground">
                                    ·
                                </span>
                                <span className="text-muted-foreground">
                                    {group.time
                                        ? group.time.slice(0, 5)
                                        : 'any time'}
                                </span>
                            </p>

                            <div className="flex flex-col gap-3">
                                {group.steps.map((step) => {
                                    const index = steps.findIndex(
                                        (item) => item.id === step.id,
                                    );
                                    const blocked = blockedStepIds.includes(
                                        step.id,
                                    );

                                    return (
                                        <div
                                            key={step.id}
                                            draggable={canUpdate}
                                            onDragStart={(event) => {
                                                event.dataTransfer.setData(
                                                    'text/plain',
                                                    String(step.id),
                                                );
                                                event.dataTransfer.effectAllowed =
                                                    'move';
                                                setDragIndex(index);
                                            }}
                                            onDragEnd={() => setDragIndex(null)}
                                            onDragOver={(event) =>
                                                event.preventDefault()
                                            }
                                            onDrop={(event) => {
                                                event.preventDefault();
                                                dropOn(index);
                                            }}
                                            data-test="campaign-step-row"
                                            className={cn(
                                                'flex items-center rounded-lg border bg-card pl-3',
                                                blocked &&
                                                    'border-destructive-foreground',
                                                dragIndex === index &&
                                                    'opacity-60',
                                            )}
                                        >
                                            <GripVertical
                                                className={cn(
                                                    'size-4 shrink-0 text-muted-foreground',
                                                    canUpdate && 'cursor-grab',
                                                )}
                                                aria-hidden
                                            />

                                            <span className="ml-4 flex size-[22px] shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground">
                                                <Mail
                                                    className="size-3"
                                                    aria-hidden
                                                />
                                            </span>

                                            <div className="ml-4 min-w-0 flex-1 py-[11px]">
                                                <p className="truncate text-body leading-5 font-semibold">
                                                    {step.subject ||
                                                        '(no subject)'}
                                                </p>
                                                <p className="text-label leading-4 text-muted-foreground">
                                                    Email ·{' '}
                                                    {step.is_threaded
                                                        ? 'Reply in thread'
                                                        : 'New thread'}
                                                </p>
                                            </div>

                                            <div
                                                data-test="campaign-step-metrics"
                                                className="grid w-[288px] shrink-0 grid-cols-3"
                                            >
                                                <Metric
                                                    label="Sent"
                                                    value={
                                                        step.metrics.sent === 0
                                                            ? '—'
                                                            : String(
                                                                  step.metrics
                                                                      .sent,
                                                              )
                                                    }
                                                />
                                                <Metric
                                                    label="Opened"
                                                    value={percent(
                                                        step.metrics
                                                            .opened_rate,
                                                    )}
                                                />
                                                <Metric
                                                    label="Replied"
                                                    value={percent(
                                                        step.metrics
                                                            .replied_rate,
                                                    )}
                                                />
                                            </div>

                                            {canUpdate ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 shrink-0 text-muted-foreground"
                                                            aria-label={`Actions for step ${step.sequence}`}
                                                        >
                                                            <MoreVertical className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                onEditStep(step)
                                                            }
                                                        >
                                                            <Pencil /> Edit step
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onSelect={() =>
                                                                moveStep(
                                                                    index,
                                                                    -1,
                                                                )
                                                            }
                                                        >
                                                            Move up
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={
                                                                index ===
                                                                steps.length - 1
                                                            }
                                                            onSelect={() =>
                                                                moveStep(
                                                                    index,
                                                                    1,
                                                                )
                                                            }
                                                        >
                                                            Move down
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                router.delete(
                                                                    destroyStep.url(
                                                                        [
                                                                            slug,
                                                                            campaign.id,
                                                                            step.id,
                                                                        ],
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 /> Delete
                                                            step
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : (
                                                <span className="w-7" />
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-label leading-4 text-muted-foreground">
                {label}
            </span>
            <span className="text-body leading-5 font-semibold tabular-nums">
                {value}
            </span>
        </div>
    );
}

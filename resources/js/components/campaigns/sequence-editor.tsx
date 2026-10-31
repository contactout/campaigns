import { router, usePage } from '@inertiajs/react';
import { Mail, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import StepForm from '@/components/campaigns/step-form';
import ConfirmDialog from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { destroy as destroyStep } from '@/routes/campaigns/steps';
import type {
    CampaignSignatureOption,
    CampaignStep,
    CampaignTemplateOption,
    MergePlaceholder,
} from '@/types';

type Props = {
    campaignId: number;
    campaignName: string;
    steps: CampaignStep[];
    /** `null` creates a new step; a step id edits that step. */
    activeStepId: number | null;
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    canUpdate: boolean;
    onSelectStep: (step: CampaignStep) => void;
    onAddStep: () => void;
    onClose: () => void;
    onDirtyChange?: (isDirty: boolean) => void;
};

const timeLabel = (step: CampaignStep): string =>
    step.time ? step.time.slice(0, 5) : 'any time';

export default function SequenceEditor({
    campaignId,
    campaignName,
    steps,
    activeStepId,
    templates,
    signatures,
    placeholders,
    canUpdate,
    onSelectStep,
    onAddStep,
    onClose,
    onDirtyChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const activeStep = steps.find((step) => step.id === activeStepId) ?? null;

    const headingRef = useRef<HTMLHeadingElement | null>(null);
    const mobileHeadingRef = useRef<HTMLHeadingElement | null>(null);
    const dirtyRef = useRef(false);
    const [pendingAction, setPendingAction] = useState<(() => void) | null>(
        null,
    );

    // The row menu that opened the editor is unmounted with it, so focus has to
    // land somewhere useful inside the pane instead of falling to <body>.
    useEffect(() => {
        // The 390 editor is a full-screen form with its own title; focus whichever
        // heading is actually displayed.
        const mobile = mobileHeadingRef.current;

        if (mobile && mobile.offsetParent !== null) {
            mobile.focus();

            return;
        }

        headingRef.current?.focus();
    }, [activeStepId]);

    const requestAction = (action: () => void) => {
        if (dirtyRef.current) {
            setPendingAction(() => action);

            return;
        }

        action();
    };

    // The board's `Esc` hint is a real affordance, but a dirty draft is not
    // discarded without asking.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape' || event.defaultPrevented) {
                return;
            }

            // Radix does not preventDefault the native Escape, so an open menu,
            // select or dialog would close the editor behind it as well.
            if (
                document.querySelector(
                    '[role="menu"],[role="listbox"],[role="dialog"]',
                )
            ) {
                return;
            }

            if (dirtyRef.current) {
                setPendingAction(() => onClose);

                return;
            }

            onClose();
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onClose]);

    const deleteStep = () => {
        if (activeStep === null) {
            return;
        }

        router.delete(destroyStep.url([slug, campaignId, activeStep.id]), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div
            data-test="campaign-sequence-editor"
            className="lg:grid lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-6"
        >
            <div
                data-test="campaign-sequence-list"
                className="hidden min-w-0 flex-col gap-3 lg:flex"
            >
                <p className="text-label leading-4 font-medium tracking-wide text-muted-foreground uppercase">
                    Sequence
                </p>

                <div className="flex flex-col gap-1.5">
                    {steps.map((step) => {
                        const active = step.id === activeStepId;

                        return (
                            <button
                                key={step.id}
                                type="button"
                                onClick={() => {
                                    if (!active) {
                                        requestAction(() => onSelectStep(step));
                                    }
                                }}
                                aria-current={active ? 'true' : undefined}
                                data-test="campaign-sequence-item"
                                className={cn(
                                    'flex items-center gap-3 rounded-md p-2 text-left transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-border',
                                    active && 'bg-accent',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-[22px] shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground',
                                        active && 'bg-background',
                                    )}
                                >
                                    <Mail className="size-3" aria-hidden />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-label leading-4 text-muted-foreground">
                                        Day {step.day ?? 0} · {timeLabel(step)}
                                    </span>
                                    <span className="block truncate text-body leading-5 font-semibold">
                                        {step.subject || '(no subject)'}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>

                {canUpdate ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        onClick={() => {
                            if (activeStep !== null) {
                                requestAction(onAddStep);
                            }
                        }}
                        data-test="campaign-add-step-button"
                    >
                        <Plus /> Add step
                    </Button>
                ) : null}
            </div>

            <div
                data-test="campaign-step-editor-pane"
                className="fixed inset-0 z-50 flex min-w-0 flex-col overflow-y-auto bg-background lg:static lg:z-auto lg:self-start lg:overflow-visible lg:rounded-lg lg:border lg:bg-card"
            >
                <div className="hidden items-start justify-between gap-4 px-4 pt-4 pb-3 lg:flex">
                    <div className="min-w-0">
                        <h2
                            ref={headingRef}
                            tabIndex={-1}
                            className="text-h3 leading-6 font-semibold outline-none focus-visible:ring-2 focus-visible:ring-primary-border"
                        >
                            {activeStep
                                ? `Edit step · Day ${activeStep.day ?? 0}`
                                : 'New step'}
                        </h2>
                        <p className="text-body leading-5 text-muted-foreground">
                            {campaignName}
                        </p>
                    </div>
                    <kbd className="text-label leading-4 font-medium text-muted-foreground">
                        Esc
                    </kbd>
                </div>

                <StepForm
                    key={activeStep?.id ?? 'new'}
                    campaignId={campaignId}
                    step={activeStep}
                    templates={templates}
                    signatures={signatures}
                    placeholders={placeholders}
                    onSuccess={onClose}
                    formClassName="flex min-h-0 flex-1 flex-col"
                    onDirtyChange={(isDirty) => {
                        dirtyRef.current = isDirty;
                        onDirtyChange?.(isDirty);
                    }}
                >
                    {({ processing, fields }) => (
                        <>
                            {/* 390: the editor is the whole screen, with Cancel, the title and
                    Save in one head row and a full-width Delete step at the end. */}
                            <div className="flex items-center gap-2 border-b px-2 py-1.5 lg:hidden">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="px-2 text-muted-foreground"
                                    onClick={() => requestAction(onClose)}
                                >
                                    Cancel
                                </Button>
                                <div className="min-w-0 flex-1 text-center">
                                    <h2
                                        ref={mobileHeadingRef}
                                        tabIndex={-1}
                                        className="truncate text-body leading-5 font-semibold outline-none"
                                    >
                                        {activeStep
                                            ? `Edit step · Day ${activeStep.day ?? 0}`
                                            : 'New step'}
                                    </h2>
                                    <p className="truncate text-label leading-4 text-muted-foreground">
                                        {campaignName}
                                    </p>
                                </div>
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    disabled={processing}
                                    data-test="campaign-save-step-button-mobile"
                                    className="px-2 text-primary"
                                >
                                    Save
                                </Button>
                            </div>

                            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 lg:flex-none lg:overflow-visible lg:border-t">
                                {fields}
                            </div>

                            {activeStep && canUpdate ? (
                                <div className="mt-auto border-t px-4 py-3 lg:hidden">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full justify-start text-destructive-foreground hover:bg-badge-danger hover:text-badge-danger-foreground"
                                        onClick={deleteStep}
                                        data-test="campaign-delete-step-button-mobile"
                                    >
                                        <Trash2 /> Delete step
                                    </Button>
                                </div>
                            ) : null}

                            <div
                                data-test="campaign-step-editor-footer"
                                className="hidden items-center justify-between gap-2 border-t px-4 py-3 lg:flex"
                            >
                                {activeStep && canUpdate ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive-foreground hover:bg-badge-danger hover:text-badge-danger-foreground"
                                        onClick={deleteStep}
                                        data-test="campaign-delete-step-button"
                                    >
                                        <Trash2 /> Delete step
                                    </Button>
                                ) : (
                                    <span />
                                )}

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => requestAction(onClose)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="campaign-save-step-button"
                                    >
                                        {activeStep ? 'Save step' : 'Add step'}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </StepForm>
            </div>

            <ConfirmDialog
                open={pendingAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingAction(null);
                    }
                }}
                title="Discard your changes?"
                description="This step has edits that have not been saved. Leaving now discards them."
                confirmLabel="Discard changes"
                onConfirm={() => {
                    const action = pendingAction;
                    setPendingAction(null);
                    action?.();
                }}
            />
        </div>
    );
}

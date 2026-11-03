import { Link } from '@inertiajs/react';
import { AlertCircle, Plug, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as connectionsIndex } from '@/routes/mailer-connections';
import type { CampaignStartReason } from '@/types';

type Props = {
    reasons: CampaignStartReason[];
    /** Called with the step id of the first step reason, when there is one. */
    onEditStep: (stepId: number) => void;
    teamSlug: string;
};

/**
 * The blocked-start panel on board 11 A.
 *
 * Every line is a reason the server derived from stored state; the panel is
 * only rendered when at least one reason blocks Start.
 */
export default function StartBlockedPanel({
    reasons,
    onEditStep,
    teamSlug,
}: Props) {
    const stepReason = reasons.find(
        (reason) => reason.type === 'step' && reason.step_id !== null,
    );
    const connectionBlocked = reasons.some(
        (reason) => reason.type === 'connection',
    );

    return (
        <section
            aria-labelledby="start-blocked-title"
            data-test="campaign-start-blocked"
            className="rounded-lg border border-destructive-foreground/30 bg-badge-danger px-4 py-4"
        >
            <div className="flex gap-3">
                <AlertCircle
                    className="mt-0.5 size-4 shrink-0 text-badge-danger-foreground"
                    aria-hidden
                />
                <div className="min-w-0">
                    <h2
                        id="start-blocked-title"
                        className="text-body leading-5 font-semibold text-badge-danger-foreground"
                    >
                        Before you can start
                    </h2>
                    <p className="text-body leading-5 text-badge-danger-foreground">
                        Fix these issues:
                    </p>

                    <ul className="mt-2 flex list-disc flex-col gap-1 pl-4 text-body leading-5 text-badge-danger-foreground">
                        {reasons.map((reason) => (
                            <li
                                key={`${reason.type}-${reason.step_id ?? reason.message}`}
                            >
                                {reason.message}
                            </li>
                        ))}
                    </ul>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {stepReason?.step_id != null ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    onEditStep(stepReason.step_id as number)
                                }
                            >
                                <Pencil /> Edit step{' '}
                                {stepReason.step_sequence ?? ''}
                            </Button>
                        ) : null}

                        {connectionBlocked ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={connectionsIndex(teamSlug)}>
                                    <Plug /> Go to Connections
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>
        </section>
    );
}

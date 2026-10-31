import { cn } from '@/lib/utils';

type Props = {
    sentToday: number;
    sendingLimit: number | null;
};

/** Share of a daily cap at which the board turns the meter red (388 / 400). */
const WARNING_RATIO = 0.9;

const formatCount = (value: number) => value.toLocaleString('en-US');

/**
 * The `Sent today` cell of the connections table.
 *
 * A connection with no daily cap has nothing to meter, so the cell renders an
 * em dash instead of a 0 / ∞ bar.
 */
export default function CapMeter({ sentToday, sendingLimit }: Props) {
    if (sendingLimit === null || sendingLimit <= 0) {
        return (
            <span className="text-body leading-5 text-muted-foreground">—</span>
        );
    }

    const ratio = sentToday / sendingLimit;
    const width = Math.min(1, Math.max(0, ratio)) * 100;
    const nearCap = ratio >= WARNING_RATIO;

    return (
        <div className="flex w-28 flex-col gap-1.5">
            <p className="text-body leading-5 whitespace-nowrap">
                <span className="font-medium">{formatCount(sentToday)}</span>{' '}
                <span className="text-muted-foreground">
                    / {formatCount(sendingLimit)}
                </span>
            </p>
            <div
                role="img"
                aria-label={`${formatCount(sentToday)} of ${formatCount(sendingLimit)} emails sent today`}
                className="h-1 w-28 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-1 rounded-full',
                        nearCap ? 'bg-destructive' : 'bg-primary',
                    )}
                    style={{ width: `${width}%` }}
                />
            </div>
        </div>
    );
}

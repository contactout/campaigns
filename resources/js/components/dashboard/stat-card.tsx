import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StatDelta = {
    value: number;
    unit: '%' | 'pt';
};

type Props = {
    label: ReactNode;
    value: string;
    delta?: StatDelta | null;
};

const formatDelta = (delta: StatDelta) => {
    const magnitude = Math.abs(delta.value).toFixed(1).replace(/\.0$/, '');
    const sign = delta.value < 0 ? '-' : '+';

    return `${sign}${magnitude}${delta.unit}`;
};

export default function StatCard({ label, value, delta }: Props) {
    return (
        <div className="rounded-lg border bg-card px-4 py-4">
            <p className="text-label leading-4 font-medium text-muted-foreground">
                {label}
            </p>
            <div className="mt-1.5 flex items-baseline gap-2">
                <p className="text-stat leading-7 font-bold">{value}</p>
                {delta ? (
                    <span
                        className={cn(
                            'text-label leading-4 font-medium',
                            delta.value > 0
                                ? 'text-badge-success-foreground'
                                : delta.value < 0
                                  ? 'text-badge-danger-foreground'
                                  : 'text-muted-foreground',
                        )}
                    >
                        {formatDelta(delta)}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

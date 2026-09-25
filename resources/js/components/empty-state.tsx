import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
    className?: string;
};

export default function EmptyState({
    title,
    description,
    icon: Icon,
    actions,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-16 text-center',
                className,
            )}
        >
            {Icon ? (
                <span className="flex size-12 items-center justify-center rounded-xl bg-muted">
                    <Icon className="size-6 text-muted-foreground" />
                </span>
            ) : null}
            <h2 className={cn('font-semibold', Icon && 'mt-4')}>{title}</h2>
            {description ? (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}
            {actions ? (
                <div className="mt-5 flex flex-wrap justify-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

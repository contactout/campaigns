import type { ComponentProps } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A product form label on the spec's label role (12px desktop, 13px on the 390
 * scale) rather than the shadcn default 14px.
 *
 * The `ui/label` primitive is left alone because the settings and auth screens
 * inherit it and are outside this redesign.
 */
export default function FormLabel({
    className,
    ...props
}: ComponentProps<typeof Label>) {
    return (
        <Label
            className={cn('text-label leading-4 font-medium', className)}
            {...props}
        />
    );
}

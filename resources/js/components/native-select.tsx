import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A native `<select>`, shaped like `Input`.
 *
 * These selects submit with their form, so the values have to stay in the DOM
 * as option elements rather than becoming a Radix listbox. What they must not
 * do is keep their own scale: below lg a control under 16px zooms the page on
 * focus in iOS Safari, and a 36px box misses the 44px touch target every other
 * primitive got. The ring is the shared one, which is 4.99:1 in light and
 * 6.10:1 in dark on the page (and 5.61:1 on a dark focused row).
 */
export default function NativeSelect({
    className,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            data-slot="native-select"
            className={cn(
                'flex h-10 w-full min-w-0 rounded-lg border border-input bg-transparent px-3 py-1 text-base transition-[color,box-shadow] outline-none max-lg:min-h-11 lg:text-body',
                'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-primary-border',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

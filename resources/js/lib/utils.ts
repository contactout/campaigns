import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge does not know the project's custom type tokens (`text-body`,
 * `text-label`, …), so its default config treats them as text colours and drops
 * them whenever a colour class follows. Registering them as font sizes keeps
 * `cn('text-body', 'text-primary-foreground')`.
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: ['h1', 'h3', 'body', 'label', 'stat', 'brand', 'caption'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

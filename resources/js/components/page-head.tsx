import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import { SidebarTrigger } from '@/components/ui/sidebar';

type Props = {
    title: string;
    description?: string;
    actions?: ReactNode;
    /**
     * Renders a back chevron before the title, as on the campaign detail boards.
     * `onClick` lets a page intercept the visit (for example to confirm before
     * discarding unsaved edits).
     */
    back?: {
        href: string;
        label: string;
        onClick?: (event: MouseEvent<Element>) => void;
    };
    /** Rendered after the title, inside the title block (for example a status pill). */
    titleAddon?: ReactNode;
    /**
     * Product pages hide the breadcrumb bar, so they render the only sidebar
     * trigger left on small screens here. Keep it until the 390 layout lands.
     */
    mobileNav?: boolean;
};

export default function PageHead({
    title,
    description,
    actions,
    back,
    titleAddon,
    mobileNav = false,
}: Props) {
    return (
        <div className="-mt-2 flex flex-col gap-3">
            {mobileNav ? (
                <SidebarTrigger className="-ml-1.5 md:hidden" />
            ) : null}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-3">
                    {back ? (
                        <Link
                            href={back.href}
                            aria-label={back.label}
                            onClick={back.onClick}
                            className="-ml-1.5 flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary-border"
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </Link>
                    ) : null}
                    <div className="min-w-0">
                        <h1 className="truncate text-h1 leading-7 font-bold">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-body leading-5 text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {titleAddon}
                </div>
                {actions ? (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

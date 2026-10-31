import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Props = {
    title: ReactNode;
    description?: string;
    /** Shown instead of `description` below lg, when the board's copy differs. */
    mobileDescription?: ReactNode;
    /** The 390 boards drop some subtitles (campaigns, templates). */
    hideDescriptionOnMobile?: boolean;
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
     * Below lg there is no sidebar: this renders the sheet trigger that opens the
     * full navigation (team switcher, user menu, Settings, sign out). Screens with
     * a back link already carry their own way out, so they do not show it.
     */
    mobileNav?: boolean;
};

export default function PageHead({
    title,
    description,
    mobileDescription,
    hideDescriptionOnMobile = false,
    actions,
    back,
    titleAddon,
    mobileNav = false,
}: Props) {
    const showTrigger = mobileNav && !back;

    return (
        <div className="-mt-2 flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3 lg:gap-4">
                <div className="flex min-w-0 items-center gap-2 lg:gap-3">
                    {showTrigger ? (
                        <SidebarTrigger
                            aria-label="Open navigation"
                            className="-ml-2 shrink-0 lg:hidden"
                        />
                    ) : null}
                    {back ? (
                        <Link
                            href={back.href}
                            aria-label={back.label}
                            onClick={back.onClick}
                            className="-ml-1.5 flex size-11 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary-border lg:size-6"
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </Link>
                    ) : null}
                    <div className="min-w-0">
                        <h1 className="truncate text-h1 leading-7 font-bold">
                            {title}
                        </h1>
                        {description ? (
                            <p
                                className={cn(
                                    'text-body leading-5 text-muted-foreground',
                                    (mobileDescription ||
                                        hideDescriptionOnMobile) &&
                                        'hidden lg:block',
                                )}
                            >
                                {description}
                            </p>
                        ) : null}
                        {mobileDescription ? (
                            <p className="text-body leading-5 text-muted-foreground lg:hidden">
                                {mobileDescription}
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

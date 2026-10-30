import type { ReactNode } from 'react';
import { SidebarTrigger } from '@/components/ui/sidebar';

type Props = {
    title: string;
    description?: string;
    actions?: ReactNode;
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
    mobileNav = false,
}: Props) {
    return (
        <div className="flex flex-col gap-3">
            {mobileNav ? (
                <SidebarTrigger className="-ml-1.5 md:hidden" />
            ) : null}
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-[20px] leading-7 font-bold tracking-[-0.01em]">
                        {title}
                    </h1>
                    {description ? (
                        <p className="text-[13px] leading-5 text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
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

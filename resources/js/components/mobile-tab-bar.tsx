import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useNavItems, type NavDestination } from '@/hooks/use-nav-items';
import { cn } from '@/lib/utils';

const itemClass =
    'flex h-full w-full flex-col items-center justify-center gap-1 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary-border focus-visible:ring-inset';

/**
 * The 390 navigation: five tabs, no sidebar, pinned to the bottom edge.
 *
 * Home / Campaigns / Contacts / Templates come straight from the shared nav
 * list; the fifth slot opens the More sheet because five labels are all the bar
 * (and the board) has room for.
 */
export default function MobileTabBar() {
    const { tabBar, more, settings } = useNavItems();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const [moreOpen, setMoreOpen] = useState(false);

    const moreActive =
        more.some((item) =>
            isCurrentOrParentUrl(item.activeMatch ?? item.href),
        ) || isCurrentOrParentUrl(settings.activeMatch ?? settings.href);

    const moreDestinations: NavDestination[] = [...more, settings];

    return (
        <>
            <nav
                aria-label="Primary"
                data-test="mobile-tab-bar"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] lg:hidden"
            >
                <ul className="flex h-14 items-stretch">
                    {tabBar.map((item) => {
                        const isMore = item.title === 'More';
                        const active = isMore
                            ? moreActive
                            : isCurrentOrParentUrl(item.href);

                        return (
                            <li key={item.title} className="flex-1">
                                {isMore ? (
                                    <button
                                        type="button"
                                        onClick={() => setMoreOpen(true)}
                                        aria-haspopup="dialog"
                                        aria-expanded={moreOpen}
                                        data-test="mobile-more-trigger"
                                        className={cn(
                                            itemClass,
                                            active
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <item.icon
                                            className="size-5"
                                            aria-hidden
                                        />
                                        <span className="text-caption leading-4 font-medium">
                                            {item.title}
                                        </span>
                                    </button>
                                ) : (
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        data-test={`mobile-tab-${item.title.toLowerCase()}`}
                                        className={cn(
                                            itemClass,
                                            active
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <item.icon
                                            className="size-5"
                                            aria-hidden
                                        />
                                        <span className="text-caption leading-4 font-medium">
                                            {item.title}
                                        </span>
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    data-test="mobile-more-sheet"
                    className="gap-0 pb-[calc(1rem+env(safe-area-inset-bottom))]"
                >
                    <SheetHeader className="pb-2">
                        <SheetTitle className="text-h3 leading-6">
                            More
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Signatures, connections and settings
                        </SheetDescription>
                    </SheetHeader>

                    <nav aria-label="More destinations" className="px-2">
                        <ul>
                            {moreDestinations.map((item) => {
                                const active = isCurrentOrParentUrl(
                                    item.activeMatch ?? item.href,
                                );

                                return (
                                    <li key={item.title}>
                                        <Link
                                            href={item.href}
                                            prefetch
                                            onClick={() => setMoreOpen(false)}
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            data-test={`mobile-more-${item.title.toLowerCase()}`}
                                            className={cn(
                                                'flex min-h-11 items-center gap-3 rounded-md px-3 text-body leading-5 font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-border',
                                                active
                                                    ? 'bg-accent text-primary'
                                                    : 'text-foreground',
                                            )}
                                        >
                                            <item.icon
                                                className={cn(
                                                    'size-4',
                                                    active
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                                aria-hidden
                                            />
                                            {item.title}
                                            <ChevronRight
                                                className="ms-auto size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>
                </SheetContent>
            </Sheet>
        </>
    );
}

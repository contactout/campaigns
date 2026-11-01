import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import MobileTabBar from '@/components/mobile-tab-bar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    showHeader = true,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                // The 390 tab bar is fixed to the bottom edge; keep the last row
                // and any sticky bar clear of it.
                className="min-w-0 overflow-x-clip pb-[calc(3.5rem+env(safe-area-inset-bottom))] lg:pb-0"
            >
                {showHeader ? (
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                ) : null}
                {children}
            </AppContent>
            <MobileTabBar />
        </AppShell>
    );
}

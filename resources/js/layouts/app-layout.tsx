import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    showHeader = true,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    showHeader?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} showHeader={showHeader}>
            {children}
        </AppLayoutTemplate>
    );
}

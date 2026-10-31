import { Link, usePage } from '@inertiajs/react';
import {
    FileText,
    LayoutGrid,
    Mail,
    PenLine,
    Send,
    Settings,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as mailerConnectionsIndex } from '@/routes/mailer-connections';
import { edit as editProfile } from '@/routes/profile';
import { index as signaturesIndex } from '@/routes/signatures';
import { index as templatesIndex } from '@/routes/templates';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const teamSlug = page.props.currentTeam?.slug;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Campaigns',
            href: teamSlug ? campaignsIndex(teamSlug) : '/',
            icon: Send,
        },
        {
            title: 'Contacts',
            href: teamSlug ? contactsIndex(teamSlug) : '/',
            icon: Users,
        },
        {
            title: 'Templates',
            href: teamSlug ? templatesIndex(teamSlug) : '/',
            icon: FileText,
        },
        {
            title: 'Signatures',
            href: teamSlug ? signaturesIndex(teamSlug) : '/',
            icon: PenLine,
        },
        {
            title: 'Connections',
            href: teamSlug ? mailerConnectionsIndex(teamSlug) : '/',
            icon: Mail,
        },
    ];

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/70">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            // Settings lives on four sibling pages (/profile, /security,
                            // /appearance, /teams), so match the section, not one page.
                            isActive={isCurrentOrParentUrl('/settings')}
                            tooltip={{ children: 'Settings' }}
                        >
                            <Link href={editProfile()} prefetch>
                                <Settings />
                                <span>Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="border-t border-sidebar-border/70 pt-2">
                    <NavUser />
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}

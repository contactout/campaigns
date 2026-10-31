import { Link, usePage } from '@inertiajs/react';
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
import { useNavItems } from '@/hooks/use-nav-items';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { sidebar, settings } = useNavItems();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const mainNavItems: NavItem[] = sidebar;
    const SettingsIcon = settings.icon;

    return (
        <Sidebar collapsible="none">
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

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            // Settings lives on four sibling pages (/profile, /security,
                            // /appearance, /teams), so the shared definition carries
                            // `activeMatch: '/settings'` and both rails read it.
                            isActive={isCurrentOrParentUrl(
                                settings.activeMatch ?? settings.href,
                            )}
                            tooltip={{ children: 'Settings' }}
                        >
                            <Link href={settings.href} prefetch>
                                <SettingsIcon />
                                <span>Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="-mx-3 border-t border-sidebar-border/70 px-3 pt-2">
                    <NavUser />
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}

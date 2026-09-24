import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FileText,
    FolderGit2,
    LayoutGrid,
    Mail,
    PenLine,
    Send,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as mailerConnectionsIndex } from '@/routes/mailer-connections';
import { index as signaturesIndex } from '@/routes/signatures';
import { index as templatesIndex } from '@/routes/templates';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
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
            title: 'Sending',
            href: teamSlug ? mailerConnectionsIndex(teamSlug) : '/',
            icon: Mail,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

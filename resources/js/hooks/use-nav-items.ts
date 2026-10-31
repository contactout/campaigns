import { usePage } from '@inertiajs/react';
import {
    FileText,
    LayoutGrid,
    Mail,
    Menu,
    PenLine,
    Send,
    Settings,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as contactsIndex } from '@/routes/contacts';
import { index as mailerConnectionsIndex } from '@/routes/mailer-connections';
import { edit as editProfile } from '@/routes/profile';
import { index as signaturesIndex } from '@/routes/signatures';
import { index as templatesIndex } from '@/routes/templates';
import type { NavItem } from '@/types';

export type NavDestination = NavItem & { icon: NonNullable<NavItem['icon']> };

export type NavItems = {
    /** The sidebar's six destinations, Dashboard first. */
    sidebar: NavDestination[];
    /** The 390 bottom bar: Home, Campaigns, Contacts, Templates, More. */
    tabBar: NavDestination[];
    /** What More opens: Signatures and Connections. Settings is separate. */
    more: NavDestination[];
    settings: NavDestination;
};

/**
 * One definition of the product navigation.
 *
 * The sidebar, the 390 bottom tab bar and its More sheet all read this, so a
 * destination cannot drift between breakpoints (label, icon or href).
 */
export function useNavItems(): NavItems {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug;

    const home: NavDestination = {
        title: 'Dashboard',
        href: slug ? dashboard(slug) : '/',
        icon: LayoutGrid,
    };
    const campaigns: NavDestination = {
        title: 'Campaigns',
        href: slug ? campaignsIndex(slug) : '/',
        icon: Send,
    };
    const contacts: NavDestination = {
        title: 'Contacts',
        href: slug ? contactsIndex(slug) : '/',
        icon: Users,
    };
    const templates: NavDestination = {
        title: 'Templates',
        href: slug ? templatesIndex(slug) : '/',
        icon: FileText,
    };
    const signatures: NavDestination = {
        title: 'Signatures',
        href: slug ? signaturesIndex(slug) : '/',
        icon: PenLine,
    };
    const connections: NavDestination = {
        title: 'Connections',
        href: slug ? mailerConnectionsIndex(slug) : '/',
        icon: Mail,
    };
    const settings: NavDestination = {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
        // One active test for the whole section: /settings/profile,
        // /settings/security, /settings/appearance and /settings/teams/*.
        activeMatch: '/settings',
    };

    return {
        sidebar: [
            home,
            campaigns,
            contacts,
            templates,
            signatures,
            connections,
        ],
        // The board labels the first tab Home; the sidebar calls the same page
        // Dashboard. Same icon and href, the board's short label on a phone.
        tabBar: [
            { ...home, title: 'Home' },
            campaigns,
            contacts,
            templates,
            { title: 'More', href: '#', icon: Menu },
        ],
        more: [signatures, connections],
        settings,
    };
}

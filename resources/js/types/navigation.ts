import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Path prefix that decides the active state, when it differs from `href`.
     * Settings is four sibling pages under `/settings`, so its link points at
     * the profile page but every one of them should mark it current.
     */
    activeMatch?: string;
};

export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type DashboardSetup = {
    has_connection: boolean;
    has_contact: boolean;
    has_campaign: boolean;
};

/**
 * The dashboard cards. Rates are null when the 30-day window has no dispatched
 * email to divide by; deltas are null when there is no comparable window.
 */
export type DashboardMetrics = {
    emails_sent: number;
    open_rate: number | null;
    reply_rate: number | null;
    active_campaigns: number;
    emails_sent_delta: number | null;
    open_rate_delta: number | null;
    reply_rate_delta: number | null;
};

/** The counts behind the "Jump back in" qualifier lines. */
export type DashboardJumpBackIn = {
    contacts: number;
    lists: number;
    active_campaigns: number;
    draft_campaigns: number;
    templates: number;
    template_folders: number;
    connections: number;
    connections_needing_attention: number;
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};

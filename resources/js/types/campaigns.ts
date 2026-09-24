export type CampaignStatus =
    | 'Draft'
    | 'Active'
    | 'Stopped'
    | 'Completed'
    | 'Archived';

export type CampaignSummary = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    steps_count: number;
    recipients_count: number;
    started_at: string | null;
    created_at: string | null;
};

export type CampaignDetail = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    timezone: string;
    mailer_connection_id: number | null;
    started_at: string | null;
    interrupted_reason: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type CampaignStep = {
    id: number;
    sequence: number;
    subject: string;
    body: string;
    day: number | null;
    time: string | null;
    is_threaded: boolean;
    setting: Record<string, unknown> | null;
};

export type CampaignStats = {
    steps_count: number;
    recipients_count: number;
    emails_sent: number;
    emails_failed: number;
    opened: number;
    clicked: number;
    unsubscribed: number;
};

export type CampaignPermissions = {
    update: boolean;
    delete: boolean;
    start: boolean;
    stop: boolean;
    archive: boolean;
    duplicate: boolean;
};

export type RecipientSummary = {
    id: number;
    contact_id: number;
    name: string;
    email: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
};

export type CampaignListOption = {
    id: number;
    name: string;
    contacts_count: number;
};

export type CampaignTemplateOption = {
    id: number;
    name: string;
    subject: string;
    body: string;
};

export type CampaignSignatureOption = {
    id: number;
    name: string;
    body: string;
    is_default: boolean;
};

export type MergePlaceholder = {
    name: string;
    label: string;
};

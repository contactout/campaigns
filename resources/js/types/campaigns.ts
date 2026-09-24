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
};

export type CampaignPermissions = {
    update: boolean;
    delete: boolean;
    start: boolean;
    stop: boolean;
    archive: boolean;
    duplicate: boolean;
};

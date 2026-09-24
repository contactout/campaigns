export type MailerConnectionStatus =
    | 'Pending'
    | 'Active'
    | 'Deactivated'
    | 'Disconnected';

export type MailerConnection = {
    id: number;
    name: string;
    mailer_type: string;
    mailer_type_label: string;
    host: string | null;
    port: number | null;
    username: string | null;
    encryption: string | null;
    from_email: string | null;
    from_name: string | null;
    imap_host: string | null;
    imap_port: number | null;
    imap_username: string | null;
    imap_encryption: string | null;
    status: string;
    status_label: string;
    sent_count: number;
    sending_limit: number | null;
    last_error: string | null;
    created_at: string | null;
};

export type MailerConnectionOption = {
    id: number;
    name: string;
    status: string;
    status_label: string;
};

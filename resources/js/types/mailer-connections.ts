export type MailerConnectionStatus =
    | 'Pending'
    | 'Active'
    | 'Deactivated'
    | 'Disconnected';

/** The status a row is shown with; see MailerConnectionDisplayStatus in PHP. */
export type MailerConnectionDisplayStatus =
    | 'Verified'
    | 'Failed'
    | 'Pending'
    | 'Deactivated'
    | 'Disconnected';

export type MailerConnection = {
    id: number;
    name: string;
    signature_id: number | null;
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
    /** Emails this connection dispatched since 00:00 UTC (one aggregate). */
    sent_today: number;
    display_status: MailerConnectionDisplayStatus;
    display_status_label: string;
    needs_attention: boolean;
    sent_count: number;
    sending_limit: number | null;
    min_seconds_between_sends: number | null;
    last_error: string | null;
    last_error_message: string | null;
    created_at: string | null;
};

export type MailerConnectionSignatureOption = {
    id: number;
    name: string;
    is_default: boolean;
};

export type MailerConnectionOption = {
    id: number;
    name: string;
    status: string;
    status_label: string;
};

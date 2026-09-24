export type ContactStatus =
    | 'NotContacted'
    | 'Contacted'
    | 'Replied'
    | 'Bounced'
    | 'Unsubscribed'
    | 'DoNotContact';

export type StatusOption = {
    value: string;
    label: string;
};

export type ListOption = {
    id: number;
    name: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

export type ContactSummary = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
    status_label: string;
    lists_count: number;
    properties: Record<string, string>;
    created_at: string | null;
};

export type ContactField = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    fallback: string | null;
};

export type ContactDetail = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
    status_label: string;
    timezone: string | null;
    source: string;
    created_at: string | null;
    updated_at: string | null;
    do_not_contact: boolean;
};

export type ContactListSummary = {
    id: number;
    name: string;
    is_default: boolean;
    contacts_count: number;
    created_at: string | null;
};

export type AvailableContact = {
    id: number;
    name: string;
    email: string | null;
};

export type ContactRecipient = {
    id: number;
    campaign: string;
    status: string;
    status_label: string;
};

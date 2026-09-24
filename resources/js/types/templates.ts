export type PlaceholderType = 'Text' | 'Number' | 'Date';

export type TemplateFolder = {
    id: number;
    name: string;
    templates_count: number;
};

export type EmailTemplateSummary = {
    id: number;
    name: string;
    subject: string;
    folder_id: number | null;
    is_draft: boolean;
    updated_at: string | null;
};

export type EmailTemplateDetail = {
    id: number;
    name: string;
    subject: string;
    body: string;
    folder_id: number | null;
    is_draft: boolean;
};

export type TemplatePlaceholder = {
    id: number;
    name: string;
    fallback: string | null;
    type: string;
    type_label: string;
};

export type Signature = {
    id: number;
    name: string;
    body: string;
    is_default: boolean;
};

export type PlaceholderTypeOption = {
    value: string;
    label: string;
};

import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { ArrowUpRight, Pencil, Trash2 } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import ContactStatusBadge from '@/components/contacts/contact-status-badge';
import InlineCell from '@/components/contacts/inline-cell';
import InlinePropertyCell from '@/components/contacts/inline-property-cell';
import InlineStatusCell from '@/components/contacts/inline-status-cell';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/hooks/use-initials';
import { show } from '@/routes/contacts';
import type {
    ContactField,
    ContactSummary,
    Paginated,
    StatusOption,
} from '@/types';

/**
 * Company and title have no first-class contact column. They are team contact
 * fields (`contact_fields`, values in `contact_properties`), so a team that
 * imported a `Company` and `Title` header gets the board's columns; a team
 * without them gets no column rather than an empty one.
 */
const COMPANY_ALIASES = [
    'company',
    'companies',
    'organization',
    'organisation',
    'employer',
];
const TITLE_ALIASES = ['title', 'job title', 'role', 'position'];

const normalize = (name: string): string =>
    name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

export function resolveContactColumns(fields: ContactField[]): {
    company: ContactField | null;
    title: ContactField | null;
    extra: ContactField[];
} {
    const company =
        fields.find((field) =>
            COMPANY_ALIASES.includes(normalize(field.name)),
        ) ?? null;
    const title =
        fields.find((field) => TITLE_ALIASES.includes(normalize(field.name))) ??
        null;

    return {
        company,
        title,
        extra: fields.filter(
            (field) => field.id !== company?.id && field.id !== title?.id,
        ),
    };
}

const CHECKBOX_WIDTH = 38;
const NAME_WIDTH = 190;
const EMAIL_WIDTH = 220;
const PHONE_WIDTH = 160;
const STATUS_WIDTH = 140;
const COMPANY_WIDTH = 150;
const TITLE_MIN_WIDTH = 76;
const EXTRA_WIDTH = 200;

const stickyHead = 'sticky top-0 z-10 bg-background';

function ContactOpenLink({
    slug,
    contact,
}: {
    slug: string;
    contact: ContactSummary;
}) {
    return (
        <Link
            href={show.url([slug, contact.id])}
            aria-label={`Open ${contact.name}`}
            title="Open contact"
            data-test="contact-open"
            className="pointer-events-none absolute top-1/2 right-0.5 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md bg-background text-muted-foreground opacity-0 outline-none focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-primary-border [@media(any-pointer:coarse)]:pointer-events-auto [@media(any-pointer:coarse)]:opacity-100"
        >
            <ArrowUpRight className="size-4" />
        </Link>
    );
}

type Props = {
    slug: string;
    contacts: Paginated<ContactSummary>;
    fields: ContactField[];
    statuses: StatusOption[];
    selected: number[];
    canManageFields: boolean;
    onToggle: (contactId: number) => void;
    onToggleAll: (checked: boolean) => void;
    onEditField: (field: ContactField) => void;
    onDeleteField: (field: ContactField) => void;
};

export default function ContactGrid({
    slug,
    contacts,
    fields,
    statuses,
    selected,
    canManageFields,
    onToggle,
    onToggleAll,
    onEditField,
    onDeleteField,
}: Props) {
    const { company, title, extra } = resolveContactColumns(fields);
    const dataFields = [company, title, ...extra].filter(
        (field): field is ContactField => field !== null,
    );
    const getInitials = useInitials();

    const lastField = dataFields[dataFields.length - 1] ?? null;
    const allSelected =
        contacts.data.length > 0 &&
        contacts.data.every((contact) => selected.includes(contact.id));
    const someSelected =
        !allSelected &&
        contacts.data.some((contact) => selected.includes(contact.id));

    const minWidth =
        CHECKBOX_WIDTH +
        NAME_WIDTH +
        EMAIL_WIDTH +
        PHONE_WIDTH +
        STATUS_WIDTH +
        (company ? COMPANY_WIDTH : 0) +
        (title ? (extra.length > 0 ? COMPANY_WIDTH : TITLE_MIN_WIDTH) : 0) +
        extra.length * EXTRA_WIDTH;

    const columnWidth = (field: ContactField): number | undefined => {
        if (field.id === lastField?.id) {
            return undefined;
        }

        return field.id === company?.id || field.id === title?.id
            ? COMPANY_WIDTH
            : EXTRA_WIDTH;
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLTableElement>) => {
        if (
            !(event.target instanceof HTMLElement) ||
            !event.target.matches('[data-grid-cell]')
        ) {
            return;
        }

        // Radix Select owns the arrow keys while its trigger has focus.
        if (event.target.closest('[role="combobox"]')) {
            return;
        }

        if (
            !['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(
                event.key,
            )
        ) {
            return;
        }

        const column = Number(event.target.closest('td')?.dataset.gridColumn);
        const row = Number(event.target.closest('tr')?.dataset.gridRow);

        if (Number.isNaN(column) || Number.isNaN(row)) {
            return;
        }

        // The status cell is a Radix Select; it owns its arrow keys.
        if (column === 3) {
            return;
        }

        const nextColumn =
            column +
            (event.key === 'ArrowRight'
                ? 1
                : event.key === 'ArrowLeft'
                  ? -1
                  : 0);
        const nextRow =
            row +
            (event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0);

        if (nextColumn < 0 || nextRow < 0) {
            return;
        }

        const next = event.currentTarget.querySelector<HTMLElement>(
            `tr[data-grid-row="${nextRow}"] td[data-grid-column="${nextColumn}"] [data-grid-cell]`,
        );

        if (next) {
            event.preventDefault();
            next.focus();
        }
    };

    return (
        <>
            {/* 390: the grid becomes stacked rows; the table keeps every column
                from lg up. */}
            <ul
                className="divide-y lg:hidden"
                aria-label="Contacts"
                data-test="contacts-list-mobile"
            >
                <li className="flex h-9 items-center gap-2 border-b px-1">
                    <span className="flex size-11 shrink-0 items-center justify-center">
                        <Checkbox
                            checked={
                                allSelected
                                    ? true
                                    : someSelected
                                      ? 'indeterminate'
                                      : false
                            }
                            onCheckedChange={(value) =>
                                onToggleAll(value === true)
                            }
                            aria-label="Select all contacts on this page"
                        />
                    </span>
                    <span className="text-label leading-4 text-muted-foreground">
                        {contacts.data.length} on this page
                    </span>
                </li>
                {contacts.data.map((contact) => {
                    const isSelected = selected.includes(contact.id);
                    const titleValue = title
                        ? contact.properties[title.id]
                        : null;
                    const companyValue = company
                        ? contact.properties[company.id]
                        : null;
                    const subline =
                        [titleValue, companyValue]
                            .filter(Boolean)
                            .join(' · ') ||
                        contact.email ||
                        '—';

                    return (
                        <li
                            key={contact.id}
                            className={cn(
                                'flex min-h-[68px] items-center gap-1 py-2 pr-4 pl-1',
                                isSelected && 'bg-accent',
                            )}
                            onClick={() => onToggle(contact.id)}
                            data-test="contact-row-mobile"
                        >
                            <span
                                className="flex size-11 shrink-0 items-center justify-center"
                                onClick={(event) => event.stopPropagation()}
                            >
                                <Checkbox
                                    checked={isSelected}
                                    onCheckedChange={() => onToggle(contact.id)}
                                    aria-label={`Select ${contact.name}`}
                                />
                            </span>
                            <span
                                className="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-caption leading-4 font-semibold text-primary"
                                aria-hidden
                            >
                                {getInitials(contact.name)}
                            </span>
                            <Link
                                href={show.url([slug, contact.id])}
                                onClick={(event) => event.stopPropagation()}
                                aria-label={`Open ${contact.name}`}
                                className="mx-2 min-w-0 flex-1 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-primary-border"
                            >
                                <span className="block truncate text-body leading-5 font-semibold">
                                    {contact.name}
                                </span>
                                <span className="block truncate text-label leading-4 text-muted-foreground">
                                    {subline}
                                </span>
                            </Link>
                            <ContactStatusBadge
                                status={contact.status}
                                label={contact.status_label}
                            />
                        </li>
                    );
                })}
            </ul>

            <div className="hidden lg:block">
                <Table
                    aria-label="Contacts"
                    data-test="contacts-grid"
                    onKeyDown={handleKeyDown}
                    className="table-fixed border-separate border-spacing-0 text-body"
                    style={{ minWidth }}
                >
                    <colgroup>
                        <col style={{ width: CHECKBOX_WIDTH }} />
                        <col style={{ width: NAME_WIDTH }} />
                        <col style={{ width: EMAIL_WIDTH }} />
                        <col style={{ width: PHONE_WIDTH }} />
                        <col style={{ width: STATUS_WIDTH }} />
                        {company ? (
                            <col style={{ width: columnWidth(company) }} />
                        ) : null}
                        {title ? (
                            <col style={{ width: columnWidth(title) }} />
                        ) : null}
                        {extra.map((field) => (
                            <col
                                key={field.id}
                                style={{ width: columnWidth(field) }}
                            />
                        ))}
                    </colgroup>

                    <TableHeader className="bg-background [&_tr]:border-0">
                        <TableRow className="hover:bg-transparent">
                            <TableHead
                                className={`${stickyHead} h-[35px] border-r border-b px-0 text-center`}
                            >
                                <span className="flex items-center justify-center">
                                    <Checkbox
                                        checked={
                                            allSelected
                                                ? true
                                                : someSelected
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(value) =>
                                            onToggleAll(value === true)
                                        }
                                        aria-label="Select all contacts on this page"
                                        data-test="contacts-select-all"
                                    />
                                </span>
                            </TableHead>

                            <TableHead
                                className={`${stickyHead} h-[35px] border-r border-b px-3`}
                            >
                                Name
                            </TableHead>
                            <TableHead
                                className={`${stickyHead} h-[35px] border-r border-b px-3`}
                            >
                                Email
                            </TableHead>
                            <TableHead
                                className={`${stickyHead} h-[35px] border-r border-b px-3`}
                            >
                                Phone
                            </TableHead>
                            <TableHead
                                className={`${stickyHead} h-[35px] border-r border-b px-3`}
                            >
                                Status
                            </TableHead>

                            {dataFields.map((field) => (
                                <TableHead
                                    key={field.id}
                                    className={`${stickyHead} group/head relative h-[35px] border-r border-b px-3`}
                                >
                                    <span
                                        className="block min-w-0 truncate"
                                        title={field.name}
                                    >
                                        {field.name}
                                    </span>
                                    {canManageFields ? (
                                        <span className="absolute top-1/2 right-1 flex -translate-y-1/2 items-center rounded bg-background pl-1 sm:opacity-0 sm:group-focus-within/head:opacity-100 sm:group-hover/head:opacity-100">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onEditField(field)
                                                }
                                                aria-label={`Edit ${field.name}`}
                                                className="rounded p-1 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <Pencil className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onDeleteField(field)
                                                }
                                                aria-label={`Delete ${field.name}`}
                                                className="rounded p-1 text-destructive-foreground hover:bg-badge-danger focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </span>
                                    ) : null}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {contacts.data.map((contact, row) => {
                            const isSelected = selected.includes(contact.id);

                            return (
                                <TableRow
                                    key={contact.id}
                                    data-grid-row={row}
                                    data-test="contact-row"
                                    data-state={
                                        isSelected ? 'selected' : undefined
                                    }
                                    className="h-[33px] border-0"
                                >
                                    <TableCell className="h-[33px] border-r border-b px-0 text-center">
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() =>
                                                onToggle(contact.id)
                                            }
                                            aria-label={`Select ${contact.name}`}
                                        />
                                    </TableCell>

                                    <TableCell
                                        data-grid-column={0}
                                        className="h-[33px] border-r border-b p-0"
                                    >
                                        <InlineCell
                                            contactId={contact.id}
                                            field="name"
                                            columnLabel="Name"
                                            value={contact.name}
                                            placeholder="Add name"
                                            className="font-medium"
                                        />
                                    </TableCell>
                                    <TableCell
                                        data-grid-column={1}
                                        className="h-[33px] border-r border-b p-0"
                                    >
                                        <InlineCell
                                            contactId={contact.id}
                                            field="email"
                                            columnLabel="Email"
                                            type="email"
                                            value={contact.email}
                                            placeholder="Add email"
                                        />
                                    </TableCell>
                                    <TableCell
                                        data-grid-column={2}
                                        className="h-[33px] border-r border-b p-0"
                                    >
                                        <InlineCell
                                            contactId={contact.id}
                                            field="phone"
                                            columnLabel="Phone"
                                            value={contact.phone}
                                            placeholder="Add phone"
                                        />
                                    </TableCell>
                                    <TableCell
                                        data-grid-column={3}
                                        className={`h-[33px] border-r border-b p-0${dataFields.length === 0 ? ' relative [&:hover>[data-test=contact-open]]:pointer-events-auto [&:hover>[data-test=contact-open]]:opacity-100' : ''}`}
                                    >
                                        <InlineStatusCell
                                            contactId={contact.id}
                                            status={contact.status}
                                            label={contact.status_label}
                                            statuses={statuses}
                                        />
                                        {dataFields.length === 0 ? (
                                            <ContactOpenLink
                                                slug={slug}
                                                contact={contact}
                                            />
                                        ) : null}
                                    </TableCell>

                                    {dataFields.map((field, index) => (
                                        <TableCell
                                            key={field.id}
                                            data-grid-column={4 + index}
                                            className={`h-[33px] border-r border-b p-0${index === dataFields.length - 1 ? ' relative [&:hover>[data-test=contact-open]]:pointer-events-auto [&:hover>[data-test=contact-open]]:opacity-100' : ''}`}
                                        >
                                            <InlinePropertyCell
                                                contactId={contact.id}
                                                fieldId={field.id}
                                                fieldName={field.name}
                                                value={
                                                    contact.properties[
                                                        field.id
                                                    ] ?? null
                                                }
                                            />
                                            {index === dataFields.length - 1 ? (
                                                <ContactOpenLink
                                                    slug={slug}
                                                    contact={contact}
                                                />
                                            ) : null}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

import { router, usePage } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { update } from '@/routes/mailer-connections/signature';
import type {
    MailerConnection,
    MailerConnectionSignatureOption,
} from '@/types';

export const TEAM_DEFAULT = 'default';

/** The select/radio value for a connection's signature. */
export function signatureValue(connection: MailerConnection): string {
    return connection.signature_id === null
        ? TEAM_DEFAULT
        : String(connection.signature_id);
}

/** An option's name, with the team default marked. */
export function signatureOptionLabel(
    signature: MailerConnectionSignatureOption,
): string {
    return signature.is_default
        ? `${signature.name} — default`
        : signature.name;
}

/** Assign a signature (or the team default) from a select/radio value. */
export function assignSignature(
    slug: string,
    connection: MailerConnection,
    next: string,
): void {
    router.put(
        update.url([slug, connection.id]),
        { signature_id: next === TEAM_DEFAULT ? null : Number(next) },
        { preserveScroll: true, preserveState: true },
    );
}

type Props = {
    connection: MailerConnection;
    signatures: MailerConnectionSignatureOption[];
    disabled?: boolean;
    /** Extra classes for the trigger, for the 390 card's inline text row. */
    triggerClassName?: string;
};

/**
 * The table's Signature cell.
 *
 * It is a select rather than the board's static text so assigning a signature
 * stays reachable from this screen, but it is styled as plain text: the board
 * shows `Sam — default`, so the trigger renders the chosen name and marks the
 * team default rather than drawing a form control in the middle of the row.
 */
export default function SignatureSelect({
    connection,
    signatures,
    disabled = false,
    triggerClassName,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const value = signatureValue(connection);

    const selected = signatures.find(
        (signature) => String(signature.id) === value,
    );

    const label =
        selected === undefined
            ? 'Team default'
            : signatureOptionLabel(selected);

    const change = (next: string) => {
        if (next === value) {
            return;
        }

        assignSignature(slug, connection, next);
    };

    return (
        <Select value={value} onValueChange={change} disabled={disabled}>
            <SelectTrigger
                size="sm"
                aria-label={`Signature for ${connection.name}`}
                title={label}
                className={cn(
                    // px-0 from lg so the value starts on the cell's 16px inset, the
                    // same x as the `Signature` column header.
                    'w-full max-w-full min-w-0 gap-1 rounded-md border-0 bg-transparent px-0.5 text-body shadow-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-border lg:px-0 dark:bg-transparent [&>span]:truncate lg:[&>svg]:hidden',
                    triggerClassName,
                )}
                data-test="connection-signature"
            >
                <SelectValue>{label}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TEAM_DEFAULT}>Team default</SelectItem>
                {signatures.map((signature) => (
                    <SelectItem key={signature.id} value={String(signature.id)}>
                        {signatureOptionLabel(signature)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

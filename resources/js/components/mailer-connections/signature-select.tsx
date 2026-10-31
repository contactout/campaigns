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

const TEAM_DEFAULT = 'default';

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

    const value =
        connection.signature_id === null
            ? TEAM_DEFAULT
            : String(connection.signature_id);

    const selected = signatures.find(
        (signature) => String(signature.id) === value,
    );

    const label =
        selected === undefined
            ? 'Team default'
            : selected.is_default
              ? `${selected.name} — default`
              : selected.name;

    const change = (next: string) => {
        if (next === value) {
            return;
        }

        router.put(
            update.url([slug, connection.id]),
            { signature_id: next === TEAM_DEFAULT ? null : Number(next) },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Select value={value} onValueChange={change} disabled={disabled}>
            <SelectTrigger
                size="sm"
                aria-label={`Signature for ${connection.name}`}
                title={label}
                className={cn(
                    'w-full max-w-full min-w-0 gap-1 rounded-md border-0 bg-transparent px-0.5 text-body shadow-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-border dark:bg-transparent [&>span]:truncate',
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
                        {signature.name}
                        {signature.is_default ? ' — default' : ''}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

import { router, usePage } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
};

export default function SignatureSelect({
    connection,
    signatures,
    disabled = false,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const value =
        connection.signature_id === null
            ? TEAM_DEFAULT
            : String(connection.signature_id);

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
                aria-label={`Signature for ${connection.name}`}
                className="h-8 w-44"
                data-test="connection-signature"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TEAM_DEFAULT}>Team default</SelectItem>
                {signatures.map((signature) => (
                    <SelectItem key={signature.id} value={String(signature.id)}>
                        {signature.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { property } from '@/routes/contacts';

type Props = {
    contactId: number;
    fieldId: number;
    value: string | null;
    className?: string;
};

export default function InlinePropertyCell({
    contactId,
    fieldId,
    value,
    className,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value ?? '');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    const cancel = () => {
        setDraft(value ?? '');
        setEditing(false);
    };

    const save = () => {
        if (draft === (value ?? '')) {
            setEditing(false);

            return;
        }

        router.patch(
            property.url([slug, contactId]),
            { contact_field_id: fieldId, value: draft },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setEditing(false),
                onError: () => setEditing(false),
            },
        );
    };

    if (!editing) {
        return (
            <button
                type="button"
                onClick={() => setEditing(true)}
                className={cn(
                    'block w-full rounded px-1 py-0.5 text-left hover:bg-muted',
                    className,
                )}
            >
                {value ? (
                    value
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </button>
        );
    }

    return (
        <input
            ref={inputRef}
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            onBlur={save}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    save();
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancel();
                }
            }}
            className={cn(
                'w-full rounded border border-input bg-background px-1 py-0.5 text-sm outline-none focus:ring-1 focus:ring-ring',
                className,
            )}
        />
    );
}

import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { cell } from '@/routes/contacts';

type Field = 'name' | 'email' | 'phone' | 'timezone';

type Props = {
    contactId: number;
    field: Field;
    value: string | null;
    type?: string;
    placeholder?: string;
    className?: string;
};

export default function InlineCell({
    contactId,
    field,
    value,
    type = 'text',
    placeholder = '—',
    className,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value ?? '');
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    const cancel = () => {
        setDraft(value ?? '');
        setError(null);
        setEditing(false);
    };

    const save = () => {
        if (draft === (value ?? '')) {
            setEditing(false);

            return;
        }

        router.patch(
            cell.url([slug, contactId]),
            { field, value: draft },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setError(null);
                    setEditing(false);
                },
                onError: (errors) => {
                    setError(errors.value ?? 'Invalid value');
                    setEditing(false);
                },
            },
        );
    };

    if (!editing) {
        return (
            <button
                type="button"
                onClick={() => setEditing(true)}
                title={error ?? undefined}
                className={cn(
                    'block w-full rounded px-1 py-0.5 text-left hover:bg-muted',
                    error && 'bg-destructive/10 text-destructive',
                    className,
                )}
            >
                {value ? (
                    value
                ) : (
                    <span className="text-muted-foreground">{placeholder}</span>
                )}
            </button>
        );
    }

    return (
        <input
            ref={inputRef}
            type={type}
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

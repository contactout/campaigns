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
    columnLabel?: string;
};

export default function InlineCell({
    contactId,
    field,
    value,
    type = 'text',
    placeholder = '—',
    className,
    columnLabel = field,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value ?? '');
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const savingRef = useRef(false);

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
        if (savingRef.current) {
            return;
        }

        if (draft === (value ?? '')) {
            setEditing(false);

            return;
        }

        savingRef.current = true;
        router.patch(
            cell.url([slug, contactId]),
            { field, value: draft },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    savingRef.current = false;
                    setError(null);
                    setEditing(false);
                    requestAnimationFrame(() => buttonRef.current?.focus());
                },
                onError: (errors) => {
                    savingRef.current = false;
                    setError(errors.value ?? 'Invalid value');
                    inputRef.current?.focus();
                },
            },
        );
    };

    if (!editing) {
        return (
            <button
                ref={buttonRef}
                type="button"
                onClick={() => {
                    setDraft(value ?? '');
                    setError(null);
                    setEditing(true);
                }}
                data-grid-cell
                aria-label={`${columnLabel}: ${value || placeholder}. Click to edit`}
                title={error ?? undefined}
                className={cn(
                    'block min-h-11 w-full truncate px-3 text-left outline-none hover:bg-muted/60 focus-visible:bg-primary/5 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset',
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
            aria-label={`Edit ${columnLabel}`}
            aria-invalid={Boolean(error)}
            title={error ?? undefined}
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
                'min-h-11 w-full border-0 bg-background px-3 text-sm ring-2 ring-primary outline-none ring-inset',
                error && 'ring-destructive',
                className,
            )}
        />
    );
}

import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { property } from '@/routes/contacts';

type Props = {
    contactId: number;
    fieldId: number;
    fieldName: string;
    value: string | null;
    className?: string;
};

export default function InlinePropertyCell({
    contactId,
    fieldId,
    fieldName,
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
                data-grid-cell
                aria-label={`${fieldName}: ${value || 'empty'}. Click to edit`}
                onClick={() => {
                    setDraft(value ?? '');
                    setEditing(true);
                }}
                className={cn(
                    'block min-h-11 w-full truncate px-3 text-left outline-none hover:bg-muted/60 focus-visible:bg-primary/5 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset',
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
            aria-label={`Edit ${fieldName}`}
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
                className,
            )}
        />
    );
}

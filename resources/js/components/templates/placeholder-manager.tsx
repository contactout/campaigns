import { Form, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FormLabel from '@/components/form-label';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { destroy, store } from '@/routes/placeholders';
import type { MergePlaceholder, TemplatePlaceholder } from '@/types';

const typeOptions = [
    { value: 'Text', label: 'Text' },
    { value: 'Number', label: 'Number' },
    { value: 'Date', label: 'Date' },
];

type Props = {
    templateId: number;
    placeholders: TemplatePlaceholder[];
    availablePlaceholders: MergePlaceholder[];
    canManage: boolean;
    /** Inserts `{{name}}` at the cursor of the body editor. */
    onInsert?: (name: string) => void;
};

/**
 * The editor's placeholders rail.
 *
 * Each row is the chip a user can insert plus the fallback the placeholder was
 * given when it was created. `placeholders.fallback` is a real column, so the
 * value column shows stored values; a placeholder without one reads an em dash
 * rather than a made-up value.
 */
export default function PlaceholderManager({
    templateId,
    placeholders,
    availablePlaceholders,
    canManage,
    onInsert,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [type, setType] = useState('Text');
    const [adding, setAdding] = useState(false);

    return (
        <div className="flex flex-col">
            <ul aria-label="Available placeholders">
                {availablePlaceholders.map((placeholder) => (
                    <li key={placeholder.name} className="border-b px-4 py-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onInsert?.(placeholder.name)}
                            aria-label={`Insert ${placeholder.label}`}
                        >
                            {`{{${placeholder.name}}}`}
                        </Button>
                    </li>
                ))}
            </ul>
            <p className="border-b px-4 py-3 text-label text-muted-foreground">
                Template definitions below do not create contact fields. Add
                custom fields in Contacts to make them available in both
                editors.
            </p>
            <ul aria-label="Template placeholders">
                {placeholders.map((placeholder) => (
                    <li
                        key={placeholder.id}
                        className={cn(
                            'group relative flex min-h-11 items-center justify-between gap-3 border-b px-4 lg:min-h-[38px]',
                            canManage && 'max-lg:pr-12',
                        )}
                    >
                        <button
                            type="button"
                            onClick={() => onInsert?.(placeholder.name)}
                            disabled={
                                !availablePlaceholders.some(
                                    (field) =>
                                        field.name.toLowerCase() ===
                                        placeholder.name.toLowerCase(),
                                )
                            }
                            title={`Insert {{${placeholder.name}}}`}
                            aria-label={
                                placeholder.fallback
                                    ? `Insert ${placeholder.name}, fallback ${placeholder.fallback}`
                                    : `Insert ${placeholder.name}`
                            }
                            className="inline-flex h-[22px] shrink-0 items-center rounded-md bg-accent px-2 text-label leading-none font-medium text-primary outline-none hover:bg-accent/80 focus-visible:ring-2 focus-visible:ring-primary-border max-lg:h-11"
                        >
                            {`{{${placeholder.name}}}`}
                        </button>

                        {/* The 390 board shows chips only. The stored fallback is
                            shown next to the chip rather than dropped, so a sighted
                            390 user keeps the same information the desktop rail has. */}
                        <span
                            className="min-w-0 flex-1 truncate text-right text-label leading-4 text-muted-foreground group-focus-within:opacity-0 group-hover:opacity-0"
                            title={placeholder.fallback ?? undefined}
                        >
                            {placeholder.fallback ?? '—'}
                        </span>

                        {canManage ? (
                            <Form
                                {...destroy.form([slug, placeholder.id])}
                                className="absolute top-1/2 right-4 -translate-y-1/2"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="icon"
                                        disabled={processing}
                                        aria-label={`Delete ${placeholder.name}`}
                                        className="size-7 text-muted-foreground max-lg:pointer-events-auto max-lg:opacity-100 lg:pointer-events-none lg:opacity-0 lg:group-focus-within:pointer-events-auto lg:group-focus-within:opacity-100 lg:group-hover:pointer-events-auto lg:group-hover:opacity-100 lg:focus-visible:pointer-events-auto lg:focus-visible:opacity-100"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </li>
                ))}
            </ul>

            {placeholders.length === 0 ? (
                <p className="border-b px-4 py-3 text-body leading-5 text-muted-foreground">
                    No template definitions yet.
                </p>
            ) : null}

            {canManage ? (
                <div className="px-4 py-3.5">
                    {adding ? (
                        <Form
                            {...store.form([slug, templateId])}
                            className="grid gap-3"
                            options={{ preserveScroll: true }}
                            onSuccess={() => {
                                setType('Text');
                                setAdding(false);
                            }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid min-w-0 gap-1.5">
                                        <FormLabel htmlFor="placeholder-name">
                                            Name
                                        </FormLabel>
                                        <Input
                                            id="placeholder-name"
                                            name="name"
                                            placeholder="first_name"
                                            required
                                            className="h-8"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid min-w-0 gap-1.5">
                                        <FormLabel htmlFor="placeholder-fallback">
                                            Fallback
                                        </FormLabel>
                                        <Input
                                            id="placeholder-fallback"
                                            name="fallback"
                                            placeholder="there"
                                            className="h-8"
                                        />
                                        <InputError message={errors.fallback} />
                                    </div>

                                    <div className="grid min-w-0 gap-1.5">
                                        <FormLabel htmlFor="placeholder-type">
                                            Type
                                        </FormLabel>
                                        <Select
                                            name="type"
                                            value={type}
                                            onValueChange={setType}
                                        >
                                            <SelectTrigger
                                                id="placeholder-type"
                                                size="sm"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {typeOptions.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.type} />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Add placeholder
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setAdding(false)}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-full"
                            onClick={() => setAdding(true)}
                            data-test="template-add-placeholder"
                        >
                            <Plus /> Add placeholder
                        </Button>
                    )}
                </div>
            ) : null}
        </div>
    );
}

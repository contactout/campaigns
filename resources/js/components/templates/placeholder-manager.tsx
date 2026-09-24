import { Form, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store } from '@/routes/placeholders';
import type { TemplatePlaceholder } from '@/types';

const typeOptions = [
    { value: 'Text', label: 'Text' },
    { value: 'Number', label: 'Number' },
    { value: 'Date', label: 'Date' },
];

type Props = {
    templateId: number;
    placeholders: TemplatePlaceholder[];
    canManage: boolean;
};

export default function PlaceholderManager({
    templateId,
    placeholders,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [type, setType] = useState('Text');

    return (
        <div className="space-y-5">
            {placeholders.length === 0 ? (
                <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                    No placeholders yet. Use them as{' '}
                    <code className="rounded bg-muted px-1 py-0.5 text-foreground">
                        {'{{name}}'}
                    </code>{' '}
                    in the body.
                </p>
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border">
                    {placeholders.map((placeholder) => (
                        <li
                            key={placeholder.id}
                            className="flex items-center justify-between gap-2 px-3 py-3 text-sm"
                        >
                            <div className="min-w-0 break-words">
                                <code className="rounded bg-muted px-1.5 py-1 font-medium text-foreground">
                                    {`{{${placeholder.name}}}`}
                                </code>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    {placeholder.type_label}
                                    {placeholder.fallback
                                        ? ` · fallback: ${placeholder.fallback}`
                                        : ''}
                                </span>
                            </div>
                            {canManage && (
                                <Form {...destroy.form([slug, placeholder.id])}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="icon"
                                            disabled={processing}
                                            aria-label={`Delete ${placeholder.name}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <Form
                    {...store.form([slug, templateId])}
                    className="grid gap-3 border-t pt-5 sm:grid-cols-2 lg:grid-cols-1"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setType('Text')}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="placeholder-name">Name</Label>
                                <Input
                                    id="placeholder-name"
                                    name="name"
                                    placeholder="first_name"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="placeholder-fallback">
                                    Fallback
                                </Label>
                                <Input
                                    id="placeholder-fallback"
                                    name="fallback"
                                    placeholder="there"
                                />
                                <InputError message={errors.fallback} />
                            </div>

                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="placeholder-type">Type</Label>
                                <Select
                                    name="type"
                                    value={type}
                                    onValueChange={setType}
                                >
                                    <SelectTrigger
                                        id="placeholder-type"
                                        className="w-full"
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

                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                className="sm:self-end"
                            >
                                <Plus /> Add
                                {recentlySuccessful ? ' (added)' : ''}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

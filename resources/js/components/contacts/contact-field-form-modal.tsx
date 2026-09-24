import { Form, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/contact-fields';
import type { ContactField } from '@/types';

type Props = {
    field?: ContactField | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const typeOptions = [
    { value: 'Text', label: 'Text' },
    { value: 'Number', label: 'Number' },
    { value: 'Date', label: 'Date' },
];

export default function ContactFieldFormModal({
    field,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const formProps = field ? update.form([slug, field.id]) : store.form(slug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${String(open)}-${field?.id ?? 'new'}`}
                    {...formProps}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {field ? 'Edit column' : 'New column'}
                                </DialogTitle>
                                <DialogDescription>
                                    Custom columns store extra contact data.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Column name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={field?.name ?? ''}
                                        placeholder="Company"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={field?.type ?? 'Text'}
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {typeOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fallback">Fallback</Label>
                                    <Input
                                        id="fallback"
                                        name="fallback"
                                        defaultValue={field?.fallback ?? ''}
                                        placeholder="Used when empty"
                                    />
                                    <InputError message={errors.fallback} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {field ? 'Save' : 'Add column'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

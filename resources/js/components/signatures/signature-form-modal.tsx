import { Form, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { store, update } from '@/routes/signatures';
import type { Signature } from '@/types';

type Props = {
    signature?: Signature | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function SignatureFormModal({
    signature,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const formProps = signature
        ? update.form([slug, signature.id])
        : store.form(slug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${String(open)}-${signature?.id ?? 'new'}`}
                    {...formProps}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {signature
                                        ? 'Edit signature'
                                        : 'New signature'}
                                </DialogTitle>
                                <DialogDescription>
                                    Signatures are appended to your emails.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={signature?.name ?? ''}
                                        placeholder="Work signature"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="body">Body</Label>
                                    <textarea
                                        id="body"
                                        name="body"
                                        defaultValue={signature?.body ?? ''}
                                        className="min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        placeholder="Best regards,&#10;Ada"
                                        required
                                    />
                                    <InputError message={errors.body} />
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="is_default"
                                        value="1"
                                        defaultChecked={signature?.is_default}
                                    />
                                    Default signature for this team
                                </label>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {signature ? 'Save' : 'Create signature'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

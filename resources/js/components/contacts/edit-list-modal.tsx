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
import { update } from '@/routes/lists';
import type { ContactListSummary } from '@/types';

type Props = {
    list: ContactListSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditListModal({ list, open, onOpenChange }: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...update.form([currentTeam?.slug ?? '', list.id])}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Edit list</DialogTitle>
                                <DialogDescription>
                                    Rename this list or change its default
                                    status.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">List name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={list.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="is_default"
                                        value="1"
                                        defaultChecked={list.is_default}
                                    />
                                    Make this the default list
                                </label>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

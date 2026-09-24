import { Form, usePage } from '@inertiajs/react';
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
import { destroy } from '@/routes/contact-fields';
import type { ContactField } from '@/types';

type Props = {
    field: ContactField;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteContactFieldModal({
    field,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...destroy.form([currentTeam?.slug ?? '', field.id])}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Delete column</DialogTitle>
                                <DialogDescription>
                                    Delete <strong>"{field.name}"</strong> and
                                    all its values? This cannot be undone.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    Delete column
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

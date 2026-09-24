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
import { destroy } from '@/routes/folders';
import type { TemplateFolder } from '@/types';

type Props = {
    folder: TemplateFolder;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteFolderModal({
    folder,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...destroy.form([currentTeam?.slug ?? '', folder.id])}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Delete folder</DialogTitle>
                                <DialogDescription>
                                    Delete <strong>"{folder.name}"</strong>?
                                    Templates inside are kept and moved out of
                                    the folder.
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
                                    Delete folder
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

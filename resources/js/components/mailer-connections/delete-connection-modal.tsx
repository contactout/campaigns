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
import { destroy } from '@/routes/mailer-connections';
import type { MailerConnection } from '@/types';

type Props = {
    connection: MailerConnection;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteConnectionModal({
    connection,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...destroy.form([currentTeam?.slug ?? '', connection.id])}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Delete connection</DialogTitle>
                                <DialogDescription>
                                    Delete <strong>"{connection.name}"</strong>?
                                    Campaigns using it will lose their sending
                                    connection.
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
                                    Delete connection
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

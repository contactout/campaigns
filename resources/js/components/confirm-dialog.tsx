import type { FocusEvent, ReactNode } from 'react';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    onConfirm: () => void;
    processing?: boolean;
    destructive?: boolean;
    /**
     * Runs when Radix restores focus after the dialog closes. A page that
     * focuses a field itself has to prevent the default, or Radix puts focus
     * back on the button that opened the dialog after it has already moved.
     */
    onCloseAutoFocus?: (event: FocusEvent<HTMLDivElement>) => void;
};

/**
 * A confirmation dialog for actions that cannot be undone.
 *
 * Built on the same `Dialog` shell as the delete modals so a destructive
 * confirmation looks the same everywhere.
 */
export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    onConfirm,
    processing = false,
    destructive = true,
    onCloseAutoFocus,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onCloseAutoFocus={onCloseAutoFocus}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

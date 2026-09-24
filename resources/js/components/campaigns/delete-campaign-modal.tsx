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
import { destroy } from '@/routes/campaigns';
import type { CampaignDetail, CampaignSummary } from '@/types';

type Props = {
    campaign: CampaignDetail | CampaignSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteCampaignModal({
    campaign,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form([currentTeam?.slug ?? '', campaign.id])}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Delete campaign</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete{' '}
                                    <strong>"{campaign.name}"</strong> and its
                                    steps. This action cannot be undone.
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
                                    Delete campaign
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

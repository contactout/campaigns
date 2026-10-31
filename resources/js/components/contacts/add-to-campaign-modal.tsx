import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/campaigns/recipients';
import type { ContactCampaignOption } from '@/types';

type Props = {
    campaigns: ContactCampaignOption[];
    contactIds: number[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess: () => void;
};

/**
 * Add the contacts selected in the grid to an existing campaign.
 *
 * The form posts the selected contact ids to the campaign recipients
 * endpoint, which already owns the permission gate and skips duplicates and
 * suppressed contacts.
 */
export default function AddToCampaignModal({
    campaigns,
    contactIds,
    open,
    onOpenChange,
    onSuccess,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [campaignId, setCampaignId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    // The `campaigns` prop is filtered to Draft and Stopped when the page loads.
    // Another member can start a listed campaign before this form is submitted,
    // so the list is re-read when the dialog opens: a campaign that started in
    // the meantime drops out of the prop, and submit refuses anything that is no
    // longer in the refreshed list.
    const [refreshing, setRefreshing] = useState(false);
    const latestCampaigns = useRef(campaigns);

    latestCampaigns.current = campaigns;

    useEffect(() => {
        if (!open) {
            return;
        }

        setRefreshing(true);
        router.reload({
            only: ['campaigns'],
            onFinish: () => setRefreshing(false),
        });
    }, [open]);

    useEffect(() => {
        if (
            campaignId !== '' &&
            !campaigns.some((campaign) => String(campaign.id) === campaignId)
        ) {
            setCampaignId('');
        }
    }, [campaigns, campaignId]);

    const submit = () => {
        if (campaignId === '' || processing || refreshing) {
            return;
        }

        const chosen = latestCampaigns.current.find(
            (campaign) => String(campaign.id) === campaignId,
        );

        if (!chosen) {
            setCampaignId('');
            setError(
                'That campaign can no longer receive contacts. Choose another campaign.',
            );

            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            store.url([slug, Number(campaignId)]),
            { contacts: contactIds },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    onOpenChange(false);
                    onSuccess();
                },
                onError: () =>
                    setError('Could not add these contacts. Try again.'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add to campaign</DialogTitle>
                    <DialogDescription>
                        {contactIds.length === 1
                            ? '1 contact will be added to the campaign you choose.'
                            : `${contactIds.length} contacts will be added to the campaign you choose.`}{' '}
                        Contacts already in the campaign are skipped. Only draft
                        and stopped campaigns can receive contacts.
                    </DialogDescription>
                </DialogHeader>

                {campaigns.length === 0 ? (
                    <p className="text-body leading-5 text-muted-foreground">
                        No campaigns yet. Create a campaign before adding
                        contacts.
                    </p>
                ) : (
                    <div className="flex flex-col gap-1.5">
                        <Select
                            value={campaignId}
                            onValueChange={setCampaignId}
                        >
                            <SelectTrigger aria-label="Campaign">
                                <SelectValue placeholder="Choose a campaign" />
                            </SelectTrigger>
                            <SelectContent>
                                {campaigns.map((campaign) => (
                                    <SelectItem
                                        key={campaign.id}
                                        value={String(campaign.id)}
                                    >
                                        <span className="flex w-full min-w-0 items-center justify-between gap-4">
                                            <span className="truncate">
                                                {campaign.name}
                                            </span>
                                            <span className="text-label text-muted-foreground">
                                                {campaign.status_label}
                                            </span>
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {error ? (
                            <p
                                role="alert"
                                className="text-body leading-5 text-destructive-foreground"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        type="button"
                        onClick={submit}
                        disabled={
                            campaigns.length === 0 ||
                            campaignId === '' ||
                            processing ||
                            refreshing
                        }
                    >
                        {processing ? 'Adding…' : 'Add to campaign'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

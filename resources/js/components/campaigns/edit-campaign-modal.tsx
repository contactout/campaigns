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
import { update } from '@/routes/campaigns';
import type { CampaignDetail, MailerConnectionOption } from '@/types';

type Props = {
    campaign: CampaignDetail;
    mailerConnections?: MailerConnectionOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditCampaignModal({
    campaign,
    mailerConnections = [],
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...update.form([currentTeam?.slug ?? '', campaign.id])}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Edit campaign</DialogTitle>
                                <DialogDescription>
                                    Update campaign details.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={campaign.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Input
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={campaign.timezone}
                                        required
                                    />
                                    <InputError message={errors.timezone} />
                                </div>

                                {mailerConnections.length > 0 ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="mailer_connection_id">
                                            Sending connection
                                        </Label>
                                        <select
                                            id="mailer_connection_id"
                                            name="mailer_connection_id"
                                            defaultValue={
                                                campaign.mailer_connection_id
                                                    ? String(
                                                          campaign.mailer_connection_id,
                                                      )
                                                    : ''
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <option value="">
                                                No connection
                                            </option>
                                            {mailerConnections.map(
                                                (connection) => (
                                                    <option
                                                        key={connection.id}
                                                        value={connection.id}
                                                    >
                                                        {connection.name}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={
                                                errors.mailer_connection_id
                                            }
                                        />
                                    </div>
                                ) : null}
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

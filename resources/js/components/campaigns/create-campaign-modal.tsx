import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/campaigns';
import type { MailerConnectionOption } from '@/types';

export default function CreateCampaignModal({
    mailerConnections = [],
    children,
}: {
    mailerConnections?: MailerConnectionOption[];
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { currentTeam } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form(currentTeam?.slug ?? '')}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>New campaign</DialogTitle>
                                <DialogDescription>
                                    Create a campaign and add email steps.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="Q4 outreach"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Input
                                        id="timezone"
                                        name="timezone"
                                        defaultValue="UTC"
                                        placeholder="UTC"
                                        required
                                    />
                                    <InputError message={errors.timezone} />
                                </div>

                                {mailerConnections.length > 0 ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="mailer_connection_id">
                                            Connection
                                        </Label>
                                        <select
                                            id="mailer_connection_id"
                                            name="mailer_connection_id"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                            defaultValue=""
                                        >
                                            <option value="">
                                                Choose later
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
                                    Create campaign
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

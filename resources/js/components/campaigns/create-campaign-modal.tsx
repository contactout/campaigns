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

export default function CreateCampaignModal({
    children,
}: {
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

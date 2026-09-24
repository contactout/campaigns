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
import { store, update } from '@/routes/campaigns/steps';
import type { CampaignStep } from '@/types';

type Props = {
    campaignId: number;
    step?: CampaignStep | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function StepFormModal({
    campaignId,
    step,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const formProps = step
        ? update.form([slug, campaignId, step.id])
        : store.form([slug, campaignId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <Form
                    key={`${String(open)}-${step?.id ?? 'new'}`}
                    {...formProps}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {step ? 'Edit step' : 'New step'}
                                </DialogTitle>
                                <DialogDescription>
                                    Email steps run in order.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input
                                        id="subject"
                                        name="subject"
                                        defaultValue={step?.subject ?? ''}
                                        placeholder="Quick question"
                                        required
                                    />
                                    <InputError message={errors.subject} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="body">Body</Label>
                                    <textarea
                                        id="body"
                                        name="body"
                                        defaultValue={step?.body ?? ''}
                                        className="min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        placeholder="Hi {{first_name}}, ..."
                                        required
                                    />
                                    <InputError message={errors.body} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="day">
                                            Day (from campaign start)
                                        </Label>
                                        <Input
                                            id="day"
                                            name="day"
                                            type="number"
                                            min={0}
                                            max={365}
                                            defaultValue={step?.day ?? 0}
                                            required
                                        />
                                        <InputError message={errors.day} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="time">
                                            Time (optional)
                                        </Label>
                                        <Input
                                            id="time"
                                            name="time"
                                            type="time"
                                            defaultValue={
                                                step?.time
                                                    ? step.time.slice(0, 5)
                                                    : ''
                                            }
                                        />
                                        <InputError message={errors.time} />
                                    </div>
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="is_threaded"
                                        value="1"
                                        defaultChecked={step?.is_threaded}
                                    />
                                    Send as a reply in the same thread
                                </label>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {step ? 'Save step' : 'Add step'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

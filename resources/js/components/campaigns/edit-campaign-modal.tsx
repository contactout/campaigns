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
import { update } from '@/routes/campaigns';
import type { CampaignDetail, MailerConnectionOption } from '@/types';

const sendingDays = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const hours = Array.from({ length: 24 }, (_, hour) => hour);

const hourLabel = (hour: number) => `${String(hour).padStart(2, '0')}:00`;

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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
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
                                            Connection
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

                                <div className="grid gap-2">
                                    <Label>Sending days</Label>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                                        {sendingDays.map((day) => (
                                            <label
                                                key={day.value}
                                                className="flex items-center gap-1.5 text-sm"
                                            >
                                                <Checkbox
                                                    name="settings[sending_days][]"
                                                    value={String(day.value)}
                                                    defaultChecked={campaign.settings.sending_days.includes(
                                                        day.value,
                                                    )}
                                                />
                                                {day.label}
                                            </label>
                                        ))}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Steps that land on another day move to
                                        the next one selected here.
                                    </p>
                                    <InputError
                                        message={
                                            errors['settings.sending_days']
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="sending_hour_from">
                                            First send hour
                                        </Label>
                                        <select
                                            id="sending_hour_from"
                                            name="settings[sending_hour_from]"
                                            defaultValue={String(
                                                campaign.settings
                                                    .sending_hour_from,
                                            )}
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {hours.map((hour) => (
                                                <option key={hour} value={hour}>
                                                    {hourLabel(hour)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors[
                                                    'settings.sending_hour_from'
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sending_hour_to">
                                            Last send hour
                                        </Label>
                                        <select
                                            id="sending_hour_to"
                                            name="settings[sending_hour_to]"
                                            defaultValue={String(
                                                campaign.settings
                                                    .sending_hour_to,
                                            )}
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {hours.map((hour) => (
                                                <option key={hour} value={hour}>
                                                    {hourLabel(hour)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors[
                                                    'settings.sending_hour_to'
                                                ]
                                            }
                                        />
                                    </div>
                                </div>

                                <p className="-mt-2 text-xs text-muted-foreground">
                                    Leave both hours at 00:00 to send at any
                                    hour.
                                </p>

                                <div className="grid gap-2">
                                    <Label>Tracking</Label>
                                    {/*
                                        The hidden input keeps an unchecked box
                                        from looking like an absent field, which
                                        would fall back to the default (on).
                                    */}
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="settings[open_tracking]"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="settings[open_tracking]"
                                            value="1"
                                            defaultChecked={
                                                campaign.settings.open_tracking
                                            }
                                        />
                                        Track opens
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="settings[link_tracking]"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="settings[link_tracking]"
                                            value="1"
                                            defaultChecked={
                                                campaign.settings.link_tracking
                                            }
                                        />
                                        Track link clicks
                                    </label>
                                </div>
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

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
import { store, update } from '@/routes/mailer-connections';
import type { MailerConnection } from '@/types';

type Props = {
    connection?: MailerConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const encryptionOptions = [
    { value: 'tls', label: 'STARTTLS (587)' },
    { value: 'ssl', label: 'SSL/TLS (465)' },
    { value: 'none', label: 'None (25)' },
];

const imapEncryptionOptions = [
    { value: 'ssl', label: 'SSL/TLS' },
    { value: 'tls', label: 'TLS' },
    { value: 'starttls', label: 'STARTTLS' },
    { value: 'none', label: 'None' },
];

export default function ConnectionFormModal({
    connection,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const formProps = connection
        ? update.form([slug, connection.id])
        : store.form(slug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <Form
                    key={`${String(open)}-${connection?.id ?? 'new'}`}
                    {...formProps}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {connection
                                        ? 'Edit SMTP connection'
                                        : 'New SMTP connection'}
                                </DialogTitle>
                                <DialogDescription>
                                    Credentials are encrypted at rest.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Connection name
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={connection?.name ?? ''}
                                        placeholder="Support inbox"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="host">SMTP host</Label>
                                        <Input
                                            id="host"
                                            name="host"
                                            defaultValue={
                                                connection?.host ?? ''
                                            }
                                            placeholder="smtp.example.com"
                                            required
                                        />
                                        <InputError message={errors.host} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="port">Port</Label>
                                        <Input
                                            id="port"
                                            name="port"
                                            type="number"
                                            defaultValue={
                                                connection?.port ?? 587
                                            }
                                            required
                                        />
                                        <InputError message={errors.port} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="encryption">
                                        Encryption
                                    </Label>
                                    <select
                                        id="encryption"
                                        name="encryption"
                                        defaultValue={
                                            connection?.encryption ?? 'tls'
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {encryptionOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.encryption} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="username">
                                            Username
                                        </Label>
                                        <Input
                                            id="username"
                                            name="username"
                                            defaultValue={
                                                connection?.username ?? ''
                                            }
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.username} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Password
                                        </Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            autoComplete="new-password"
                                            placeholder={
                                                connection
                                                    ? 'Leave blank to keep current'
                                                    : ''
                                            }
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="from_email">
                                            From email
                                        </Label>
                                        <Input
                                            id="from_email"
                                            name="from_email"
                                            type="email"
                                            defaultValue={
                                                connection?.from_email ?? ''
                                            }
                                            placeholder="you@example.com"
                                            required
                                        />
                                        <InputError
                                            message={errors.from_email}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="from_name">
                                            From name
                                        </Label>
                                        <Input
                                            id="from_name"
                                            name="from_name"
                                            defaultValue={
                                                connection?.from_name ?? ''
                                            }
                                            placeholder="Your name"
                                        />
                                        <InputError
                                            message={errors.from_name}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-4 rounded-md border p-4">
                                    <div>
                                        <p className="text-sm font-medium">
                                            Inbox (IMAP)
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Optional. Used to detect replies and
                                            bounces.
                                        </p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="imap_host">
                                                IMAP host
                                            </Label>
                                            <Input
                                                id="imap_host"
                                                name="imap_host"
                                                defaultValue={
                                                    connection?.imap_host ?? ''
                                                }
                                                placeholder="imap.example.com"
                                            />
                                            <InputError
                                                message={errors.imap_host}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="imap_port">
                                                Port
                                            </Label>
                                            <Input
                                                id="imap_port"
                                                name="imap_port"
                                                type="number"
                                                defaultValue={
                                                    connection?.imap_port ?? 993
                                                }
                                            />
                                            <InputError
                                                message={errors.imap_port}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="imap_encryption">
                                            Encryption
                                        </Label>
                                        <select
                                            id="imap_encryption"
                                            name="imap_encryption"
                                            defaultValue={
                                                connection?.imap_encryption ??
                                                'ssl'
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {imapEncryptionOptions.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={errors.imap_encryption}
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="imap_username">
                                                Username
                                            </Label>
                                            <Input
                                                id="imap_username"
                                                name="imap_username"
                                                defaultValue={
                                                    connection?.imap_username ??
                                                    ''
                                                }
                                                autoComplete="off"
                                            />
                                            <InputError
                                                message={errors.imap_username}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="imap_password">
                                                Password
                                            </Label>
                                            <Input
                                                id="imap_password"
                                                name="imap_password"
                                                type="password"
                                                autoComplete="new-password"
                                                placeholder={
                                                    connection
                                                        ? 'Leave blank to keep current'
                                                        : ''
                                                }
                                            />
                                            <InputError
                                                message={errors.imap_password}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {connection ? 'Save' : 'Create connection'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

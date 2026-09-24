import { Form, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
import { attach } from '@/routes/lists/contacts';
import type { AvailableContact, ContactListSummary } from '@/types';

type Props = {
    list: ContactListSummary;
    availableContacts: AvailableContact[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function AddContactsToListModal({
    list,
    availableContacts,
    open,
    onOpenChange,
}: Props) {
    const [search, setSearch] = useState('');
    const { currentTeam } = usePage().props;

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return availableContacts;
        }

        return availableContacts.filter(
            (contact) =>
                contact.name.toLowerCase().includes(term) ||
                (contact.email ?? '').toLowerCase().includes(term),
        );
    }, [availableContacts, search]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-hidden">
                <Form
                    key={String(open)}
                    {...attach.form([currentTeam?.slug ?? '', list.id])}
                    className="flex max-h-[75vh] flex-col space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add contacts</DialogTitle>
                                <DialogDescription>
                                    Select contacts to add to "{list.name}".
                                </DialogDescription>
                            </DialogHeader>

                            <Input
                                placeholder="Search contacts..."
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />

                            <div className="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                                {filtered.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        No contacts available.
                                    </p>
                                ) : (
                                    filtered.map((contact) => (
                                        <label
                                            key={contact.id}
                                            className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                        >
                                            <Checkbox
                                                name="contacts[]"
                                                value={String(contact.id)}
                                            />
                                            <span className="flex flex-col">
                                                <span className="font-medium">
                                                    {contact.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {contact.email}
                                                </span>
                                            </span>
                                        </label>
                                    ))
                                )}
                            </div>

                            <InputError message={errors.contacts} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    Add to list
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

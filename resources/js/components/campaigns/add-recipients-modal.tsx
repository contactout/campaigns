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
import { store } from '@/routes/campaigns/recipients';
import type { AvailableContact, CampaignListOption } from '@/types';

type Props = {
    campaignId: number;
    availableContacts: AvailableContact[];
    lists: CampaignListOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function AddRecipientsModal({
    campaignId,
    availableContacts,
    lists,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [tab, setTab] = useState<'contacts' | 'lists'>('contacts');
    const [search, setSearch] = useState('');

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
                    {...store.form([slug, campaignId])}
                    className="flex max-h-[75vh] flex-col space-y-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add recipients</DialogTitle>
                                <DialogDescription>
                                    Add contacts or whole lists to this
                                    campaign.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex gap-1">
                                <Button
                                    type="button"
                                    variant={
                                        tab === 'contacts' ? 'default' : 'ghost'
                                    }
                                    size="sm"
                                    onClick={() => setTab('contacts')}
                                >
                                    Contacts
                                </Button>
                                <Button
                                    type="button"
                                    variant={
                                        tab === 'lists' ? 'default' : 'ghost'
                                    }
                                    size="sm"
                                    onClick={() => setTab('lists')}
                                >
                                    Lists
                                </Button>
                            </div>

                            {tab === 'contacts' ? (
                                <>
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
                                                        value={String(
                                                            contact.id,
                                                        )}
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
                                </>
                            ) : (
                                <div className="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                                    {lists.length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No lists yet.
                                        </p>
                                    ) : (
                                        lists.map((list) => (
                                            <label
                                                key={list.id}
                                                className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                            >
                                                <Checkbox
                                                    name="lists[]"
                                                    value={String(list.id)}
                                                />
                                                <span className="font-medium">
                                                    {list.name}
                                                </span>
                                                <span className="ml-auto text-muted-foreground">
                                                    {list.contacts_count}
                                                </span>
                                            </label>
                                        ))
                                    )}
                                </div>
                            )}

                            <InputError
                                message={errors.contacts ?? errors.lists}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    Add recipients
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

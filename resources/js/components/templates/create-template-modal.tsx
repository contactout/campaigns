import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import NativeSelect from '@/components/native-select';
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
import { store } from '@/routes/templates';
import type { TemplateFolder } from '@/types';

type Props = {
    folders: TemplateFolder[];
    children: React.ReactNode;
};

export default function CreateTemplateModal({ folders, children }: Props) {
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
                                <DialogTitle>New template</DialogTitle>
                                <DialogDescription>
                                    Save a reusable email you can apply to
                                    campaign steps.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="Intro outreach"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input
                                        id="subject"
                                        name="subject"
                                        placeholder="Quick question"
                                    />
                                    <InputError message={errors.subject} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="folder_id">Folder</Label>
                                    <NativeSelect
                                        id="folder_id"
                                        name="folder_id"
                                        defaultValue=""
                                    >
                                        <option value="">No folder</option>
                                        {folders.map((folder) => (
                                            <option
                                                key={folder.id}
                                                value={folder.id}
                                            >
                                                {folder.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.folder_id} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    Create template
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

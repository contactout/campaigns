import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import PlaceholderManager from '@/components/templates/placeholder-manager';
import RichTextEditor from '@/components/templates/rich-text-editor';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/templates';
import type {
    EmailTemplateDetail,
    TemplateFolder,
    TemplatePlaceholder,
} from '@/types';

type Props = {
    template: EmailTemplateDetail;
    placeholders: TemplatePlaceholder[];
    folders: TemplateFolder[];
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function TemplateShow({
    template,
    placeholders,
    folders,
    can,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title={template.name} />

            <h1 className="sr-only">{template.name}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading variant="small" title={template.name} />

                    <div className="flex items-center gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={index(slug)}>Back to templates</Link>
                        </Button>
                        {can.delete ? (
                            <Button
                                variant="destructive"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2 /> Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                <Form
                    {...update.form([slug, template.id])}
                    className="space-y-4"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={template.name}
                                        required
                                        disabled={!can.update}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input
                                        id="subject"
                                        name="subject"
                                        defaultValue={template.subject}
                                        disabled={!can.update}
                                    />
                                    <InputError message={errors.subject} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="folder_id">Folder</Label>
                                <select
                                    id="folder_id"
                                    name="folder_id"
                                    defaultValue={
                                        template.folder_id
                                            ? String(template.folder_id)
                                            : ''
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    disabled={!can.update}
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
                                </select>
                                <InputError message={errors.folder_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Body</Label>
                                <RichTextEditor
                                    name="body"
                                    value={template.body}
                                />
                                <InputError message={errors.body} />
                            </div>

                            {can.update ? (
                                <div className="flex items-center gap-3">
                                    <Button disabled={processing}>
                                        Save template
                                    </Button>
                                    {recentlySuccessful ? (
                                        <span className="text-sm text-muted-foreground">
                                            Saved.
                                        </span>
                                    ) : null}
                                </div>
                            ) : null}
                        </>
                    )}
                </Form>

                <Card>
                    <CardHeader>
                        <CardTitle>Placeholders</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <PlaceholderManager
                            templateId={template.id}
                            placeholders={placeholders}
                        />
                    </CardContent>
                </Card>
            </div>

            {deleteOpen ? (
                <DeleteTemplateModal
                    template={template}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            ) : null}
        </>
    );
}

TemplateShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Templates',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

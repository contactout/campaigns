import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import PlaceholderManager from '@/components/templates/placeholder-manager';
import RichTextEditor from '@/components/templates/rich-text-editor';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Heading variant="small" title={template.name} />
                        {template.is_draft && (
                            <Badge variant="secondary">Draft</Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={index(slug)}>
                                <ArrowLeft /> Templates
                            </Link>
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

                <div className="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Form
                        {...update.form([slug, template.id])}
                        className="min-w-0 space-y-6 rounded-xl border bg-card p-5 sm:p-6"
                        options={{ preserveScroll: true }}
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-5 sm:grid-cols-2">
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
                                    <Label id="template-body-label">Body</Label>
                                    <RichTextEditor
                                        name="body"
                                        value={template.body}
                                        labelledBy="template-body-label"
                                        readOnly={!can.update}
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

                    <Card className="min-w-0 shadow-sm">
                        <CardHeader>
                            <CardTitle>Placeholders</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Use these fields in your email body.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderManager
                                templateId={template.id}
                                placeholders={placeholders}
                                canManage={can.update}
                            />
                        </CardContent>
                    </Card>
                </div>
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

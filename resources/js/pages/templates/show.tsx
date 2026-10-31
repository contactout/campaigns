import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import FormLabel from '@/components/form-label';
import InputError from '@/components/input-error';
import PageHead from '@/components/page-head';
import PlaceholderManager from '@/components/templates/placeholder-manager';
import RichTextEditor from '@/components/templates/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { index, update } from '@/routes/templates';
import type {
    EmailTemplateDetail,
    TemplateFolder,
    TemplatePlaceholder,
} from '@/types';

const NO_FOLDER = 'none';

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
    const listUrl = index.url(slug);

    const form = useForm({
        name: template.name,
        subject: template.subject ?? '',
        folder_id:
            template.folder_id === null
                ? NO_FOLDER
                : String(template.folder_id),
        body: template.body,
    });

    const [saved, setSaved] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<(() => void) | null>(
        null,
    );
    const nameRef = useRef<HTMLInputElement | null>(null);
    const discardedRef = useRef(false);
    const insertRef = useRef<((text: string) => void) | null>(null);

    const initialFolder =
        template.folder_id === null ? NO_FOLDER : String(template.folder_id);

    const isDirty =
        form.data.name !== template.name ||
        form.data.subject !== (template.subject ?? '') ||
        form.data.folder_id !== initialFolder ||
        form.data.body !== template.body;

    // A closed tab or a hard reload cannot be intercepted by a dialog; the
    // browser's own confirmation is the only guard there. Inertia visits started
    // elsewhere on the page (the sidebar) are not guarded.
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [isDirty]);

    const guard = (action: () => void) => {
        if (isDirty) {
            setPendingAction(() => action);

            return;
        }

        action();
    };

    const discard = () => {
        form.reset();
        form.clearErrors();
        setSaved(false);
        nameRef.current?.focus();
    };

    const submit = () => {
        if (!can.update) {
            return;
        }

        form.transform((data) => ({
            ...data,
            folder_id: data.folder_id === NO_FOLDER ? null : data.folder_id,
        }));
        form.patch(update.url([slug, template.id]), {
            preserveScroll: true,
            onSuccess: () => setSaved(true),
        });
    };

    const folderName =
        folders.find((folder) => String(folder.id) === form.data.folder_id)
            ?.name ?? 'No folder';

    const description = saved ? `${folderName} · Saved just now` : folderName;

    const insertPlaceholder = (name: string) => {
        insertRef.current?.(`{{${name}}}`);
    };

    return (
        <>
            <Head title={form.data.name || template.name} />

            <div className="flex w-full flex-col gap-5 px-4 py-6 lg:px-6">
                <PageHead
                    title={
                        <>
                            <span className="lg:hidden">Edit template</span>
                            <span className="hidden lg:inline">
                                {form.data.name.trim() || 'Untitled template'}
                            </span>
                        </>
                    }
                    description={description}
                    mobileNav
                    back={{
                        href: listUrl,
                        label: 'Back to templates',
                        onClick: (event) => {
                            if (!isDirty) {
                                return;
                            }

                            event.preventDefault();
                            guard(() => router.visit(listUrl));
                        },
                    }}
                    titleAddon={
                        template.is_draft ? (
                            <span className="hidden lg:inline-flex">
                                <Badge variant="neutral">Draft</Badge>
                            </span>
                        ) : null
                    }
                    actions={
                        can.update || can.delete ? (
                            <>
                                {can.update ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => guard(discard)}
                                        disabled={form.processing}
                                        data-test="template-discard-button"
                                        className="hidden lg:inline-flex"
                                    >
                                        Discard
                                    </Button>
                                ) : null}
                                {can.update ? (
                                    <Button
                                        type="button"
                                        onClick={submit}
                                        disabled={form.processing}
                                        aria-label="Save template"
                                        data-test="template-save-button"
                                        className="max-lg:border-0 max-lg:bg-transparent max-lg:px-2 max-lg:text-primary max-lg:hover:bg-accent max-lg:focus-visible:border-transparent"
                                    >
                                        <span className="hidden lg:inline">
                                            Save template
                                        </span>
                                        <span className="lg:hidden">Save</span>
                                    </Button>
                                ) : null}
                                {can.update || can.delete ? (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="More template actions"
                                                data-test="template-more-button"
                                            >
                                                <MoreHorizontal className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            {can.update ? (
                                                <DropdownMenuItem
                                                    className="lg:hidden"
                                                    onSelect={() =>
                                                        guard(discard)
                                                    }
                                                >
                                                    Discard changes
                                                </DropdownMenuItem>
                                            ) : null}
                                            {can.delete ? (
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        setDeleteOpen(true)
                                                    }
                                                >
                                                    <Trash2 /> Delete template
                                                </DropdownMenuItem>
                                            ) : null}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                ) : null}
                            </>
                        ) : null
                    }
                />

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_299px]">
                    <form
                        className="min-w-0"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
                            <div className="grid min-w-0 gap-1.5">
                                <FormLabel htmlFor="name">Name</FormLabel>
                                <Input
                                    id="name"
                                    ref={nameRef}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                    disabled={!can.update}
                                    aria-invalid={Boolean(form.errors.name)}
                                    className="h-9"
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid min-w-0 gap-1.5">
                                <FormLabel htmlFor="folder_id">
                                    Folder
                                </FormLabel>
                                <select
                                    id="folder_id"
                                    value={form.data.folder_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'folder_id',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!can.update}
                                    className="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-body outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-primary-border disabled:pointer-events-none disabled:opacity-50"
                                >
                                    <option value={NO_FOLDER}>No folder</option>
                                    {folders.map((folder) => (
                                        <option
                                            key={folder.id}
                                            value={String(folder.id)}
                                        >
                                            {folder.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.folder_id} />
                            </div>
                        </div>

                        <div className="mt-4 grid min-w-0 gap-1.5">
                            <FormLabel htmlFor="subject">Subject</FormLabel>
                            <Input
                                id="subject"
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                disabled={!can.update}
                                aria-invalid={Boolean(form.errors.subject)}
                                className="h-9"
                            />
                            <InputError message={form.errors.subject} />
                        </div>

                        <div className="mt-4 grid min-w-0 gap-1.5">
                            <FormLabel id="template-body-label">Body</FormLabel>
                            <RichTextEditor
                                name="body"
                                value={form.data.body}
                                onChange={(html) => form.setData('body', html)}
                                labelledBy="template-body-label"
                                readOnly={!can.update}
                                toolbar="board"
                                minHeightClass="min-h-[280px]"
                                insertRef={insertRef}
                                actions={
                                    placeholders.length > 0 ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label="Insert placeholder"
                                                >
                                                    <Plus />
                                                    <span className="lg:hidden">
                                                        Insert
                                                    </span>
                                                    <span className="hidden lg:inline">
                                                        Placeholder
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {placeholders.map(
                                                    (placeholder) => (
                                                        <DropdownMenuItem
                                                            key={placeholder.id}
                                                            onSelect={() =>
                                                                insertPlaceholder(
                                                                    placeholder.name,
                                                                )
                                                            }
                                                        >
                                                            {
                                                                placeholder.type_label
                                                            }{' '}
                                                            · {placeholder.name}
                                                        </DropdownMenuItem>
                                                    ),
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : null
                                }
                            />
                            <InputError message={form.errors.body} />
                        </div>
                    </form>

                    <section
                        aria-label="Placeholders"
                        className="min-w-0 overflow-hidden rounded-lg border bg-card"
                    >
                        <div className="px-4 pt-4 pb-3">
                            <h2 className="text-h3 leading-6 font-semibold">
                                Placeholders
                            </h2>
                            <p className="mt-1 hidden text-label leading-4 text-muted-foreground lg:block">
                                Click to insert at cursor. Fallback used when a
                                contact field is empty.
                            </p>
                        </div>
                        <PlaceholderManager
                            templateId={template.id}
                            placeholders={placeholders}
                            canManage={can.update}
                            onInsert={insertPlaceholder}
                        />
                    </section>
                </div>
            </div>

            <ConfirmDialog
                open={pendingAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingAction(null);
                    }
                }}
                title="Discard your changes?"
                description="This template has edits that have not been saved. Leaving now discards them."
                confirmLabel="Discard changes"
                onCloseAutoFocus={(event) => {
                    // Radix would put focus back on the Discard button after the
                    // dialog closes, undoing discard()'s own focus target. Only the
                    // confirmed discard has moved focus itself; on Cancel, leave
                    // Radix's restore alone so focus returns to whatever opened the
                    // dialog (the Discard button, the list menu or the back link).
                    if (!discardedRef.current) {
                        return;
                    }

                    discardedRef.current = false;
                    event.preventDefault();
                    nameRef.current?.focus();
                }}
                onConfirm={() => {
                    const action = pendingAction;
                    discardedRef.current = true;
                    setPendingAction(null);
                    action?.();
                }}
            />

            <DeleteTemplateModal
                template={template}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
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
    showHeader: false,
});

import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, FolderClosed, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import EmptyState from '@/components/empty-state';
import CreateTemplateModal from '@/components/templates/create-template-modal';
import DeleteFolderModal from '@/components/templates/delete-folder-modal';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import FolderFormModal from '@/components/templates/folder-form-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/templates';
import type { EmailTemplateSummary, TemplateFolder } from '@/types';

type Props = {
    templates: EmailTemplateSummary[];
    folders: TemplateFolder[];
    can: {
        create: boolean;
    };
};

export default function TemplatesIndex({ templates, folders, can }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [selectedFolder, setSelectedFolder] = useState<number | null>(null);
    const [folderModalOpen, setFolderModalOpen] = useState(false);
    const [editingFolder, setEditingFolder] = useState<TemplateFolder | null>(
        null,
    );
    const [deletingFolder, setDeletingFolder] = useState<TemplateFolder | null>(
        null,
    );
    const [deletingTemplate, setDeletingTemplate] =
        useState<EmailTemplateSummary | null>(null);

    const filtered = useMemo(
        () =>
            selectedFolder === null
                ? templates
                : templates.filter(
                      (template) => template.folder_id === selectedFolder,
                  ),
        [templates, selectedFolder],
    );

    const openNewFolder = () => {
        setEditingFolder(null);
        setFolderModalOpen(true);
    };

    const openEditFolder = (folder: TemplateFolder) => {
        setEditingFolder(folder);
        setFolderModalOpen(true);
    };

    return (
        <>
            <Head title="Templates" />

            <h1 className="sr-only">Templates</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Templates"
                        description="Organize reusable emails for your campaigns"
                    />

                    {can.create ? (
                        <CreateTemplateModal folders={folders}>
                            <Button data-test="templates-new-button">
                                <Plus /> New template
                            </Button>
                        </CreateTemplateModal>
                    ) : null}
                </div>

                <div className="grid min-w-0 gap-6 md:grid-cols-[220px_minmax(0,1fr)]">
                    <aside
                        aria-label="Template folders"
                        className="min-w-0 space-y-1 rounded-xl border bg-card p-2 md:self-start"
                    >
                        <button
                            type="button"
                            onClick={() => setSelectedFolder(null)}
                            aria-current={
                                selectedFolder === null ? 'page' : undefined
                            }
                            className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${
                                selectedFolder === null
                                    ? 'bg-muted font-medium'
                                    : 'hover:bg-muted/60'
                            }`}
                        >
                            <span className="flex items-center gap-2">
                                <FileText className="size-4 text-muted-foreground" />{' '}
                                All templates
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {templates.length}
                            </span>
                        </button>

                        {folders.map((folder) => (
                            <div
                                key={folder.id}
                                className={`group flex min-w-0 items-center justify-between rounded-md px-3 py-1 text-sm transition-colors ${
                                    selectedFolder === folder.id
                                        ? 'bg-muted font-medium'
                                        : 'hover:bg-muted/60'
                                }`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setSelectedFolder(folder.id)}
                                    aria-current={
                                        selectedFolder === folder.id
                                            ? 'page'
                                            : undefined
                                    }
                                    className="flex min-w-0 flex-1 items-center gap-2 py-1 text-left focus-visible:rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <FolderClosed className="size-4 shrink-0 text-muted-foreground" />
                                    <span
                                        className="truncate"
                                        title={folder.name}
                                    >
                                        {folder.name}
                                    </span>
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        {folder.templates_count}
                                    </span>
                                </button>
                                {can.create ? (
                                    <span className="flex shrink-0 items-center sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Rename ${folder.name}`}
                                            onClick={() =>
                                                openEditFolder(folder)
                                            }
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Delete ${folder.name}`}
                                            onClick={() =>
                                                setDeletingFolder(folder)
                                            }
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </span>
                                ) : null}
                            </div>
                        ))}

                        {can.create ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="w-full justify-start"
                                onClick={openNewFolder}
                            >
                                <Plus className="h-4 w-4" /> New folder
                            </Button>
                        ) : null}
                    </aside>

                    <section aria-label="Templates" className="min-w-0">
                        {filtered.length === 0 ? (
                            <EmptyState
                                className="min-h-64 py-12"
                                icon={FileText}
                                title={
                                    selectedFolder === null
                                        ? 'No templates yet'
                                        : 'This folder is empty'
                                }
                                description={
                                    selectedFolder === null
                                        ? 'Create a template to reuse your best emails.'
                                        : 'Move a template here or create a new one.'
                                }
                                actions={
                                    can.create ? (
                                        <CreateTemplateModal folders={folders}>
                                            <Button>
                                                <Plus /> New template
                                            </Button>
                                        </CreateTemplateModal>
                                    ) : null
                                }
                            />
                        ) : (
                            <div className="flex flex-col gap-3">
                                {filtered.map((template) => (
                                    <div
                                        key={template.id}
                                        className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-foreground/20 hover:bg-muted/20 sm:p-5"
                                        data-test="template-row"
                                    >
                                        <Link
                                            href={show([slug, template.id])}
                                            className="flex min-w-0 flex-1 items-center gap-4 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                <FileText className="size-5 text-muted-foreground" />
                                            </span>
                                            <span className="flex min-w-0 flex-col gap-0.5">
                                                <span className="flex flex-wrap items-center gap-2 font-semibold">
                                                    {template.name}
                                                    {template.is_draft && (
                                                        <Badge variant="secondary">
                                                            Draft
                                                        </Badge>
                                                    )}
                                                </span>
                                                <span className="truncate text-sm text-muted-foreground">
                                                    {template.subject ||
                                                        'No subject'}
                                                </span>
                                            </span>
                                        </Link>

                                        {can.create ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${template.name}`}
                                                onClick={() =>
                                                    setDeletingTemplate(
                                                        template,
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {folderModalOpen ? (
                <FolderFormModal
                    folder={editingFolder}
                    open={folderModalOpen}
                    onOpenChange={setFolderModalOpen}
                />
            ) : null}

            {deletingFolder ? (
                <DeleteFolderModal
                    folder={deletingFolder}
                    open={deletingFolder !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeletingFolder(null);
                        }
                    }}
                />
            ) : null}

            {deletingTemplate ? (
                <DeleteTemplateModal
                    template={deletingTemplate}
                    open={deletingTemplate !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeletingTemplate(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}

TemplatesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Templates',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

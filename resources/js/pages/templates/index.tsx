import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateTemplateModal from '@/components/templates/create-template-modal';
import DeleteFolderModal from '@/components/templates/delete-folder-modal';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import FolderFormModal from '@/components/templates/folder-form-modal';
import Heading from '@/components/heading';
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

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Templates"
                        description="Reusable emails for your campaigns"
                    />

                    {can.create ? (
                        <CreateTemplateModal folders={folders}>
                            <Button data-test="templates-new-button">
                                <Plus /> New template
                            </Button>
                        </CreateTemplateModal>
                    ) : null}
                </div>

                <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                    <aside className="space-y-1">
                        <button
                            type="button"
                            onClick={() => setSelectedFolder(null)}
                            className={`w-full rounded-md px-3 py-2 text-left text-sm ${
                                selectedFolder === null
                                    ? 'bg-muted font-medium'
                                    : 'hover:bg-muted/60'
                            }`}
                        >
                            All templates
                            <span className="ml-2 text-xs text-muted-foreground">
                                {templates.length}
                            </span>
                        </button>

                        {folders.map((folder) => (
                            <div
                                key={folder.id}
                                className={`group flex items-center justify-between rounded-md px-3 py-2 text-sm ${
                                    selectedFolder === folder.id
                                        ? 'bg-muted font-medium'
                                        : 'hover:bg-muted/60'
                                }`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setSelectedFolder(folder.id)}
                                    className="flex flex-1 items-center gap-2 text-left"
                                >
                                    {folder.name}
                                    <span className="text-xs text-muted-foreground">
                                        {folder.templates_count}
                                    </span>
                                </button>
                                {can.create ? (
                                    <span className="flex items-center opacity-0 group-hover:opacity-100">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                openEditFolder(folder)
                                            }
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
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

                    <div>
                        {filtered.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                No templates here yet.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {filtered.map((template) => (
                                    <div
                                        key={template.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-4"
                                        data-test="template-row"
                                    >
                                        <Link
                                            href={show([slug, template.id])}
                                            className="flex flex-1 items-center gap-3"
                                        >
                                            <FileText className="h-4 w-4 text-muted-foreground" />
                                            <span className="flex flex-col">
                                                <span className="font-medium">
                                                    {template.name}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {template.subject ||
                                                        'No subject'}
                                                </span>
                                            </span>
                                        </Link>

                                        {can.create ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
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
                    </div>
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

import { Head, Link, usePage } from '@inertiajs/react';
import {
    FileText,
    FolderClosed,
    FolderPlus,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHead from '@/components/page-head';
import CreateTemplateModal from '@/components/templates/create-template-modal';
import DeleteFolderModal from '@/components/templates/delete-folder-modal';
import DeleteTemplateModal from '@/components/templates/delete-template-modal';
import FolderFormModal from '@/components/templates/folder-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/templates';
import type { EmailTemplateSummary, TemplateFolder } from '@/types';

type Props = {
    templates: EmailTemplateSummary[];
    folders: TemplateFolder[];
    can: {
        create: boolean;
    };
};

type Group = {
    key: string;
    folder: TemplateFolder | null;
    templates: EmailTemplateSummary[];
};

/** The board's `Updated Oct 12` subline. */
function updatedLabel(updatedAt: string | null): string | null {
    if (!updatedAt) {
        return null;
    }

    return `Updated ${new Date(updatedAt).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    })}`;
}

function RowMenu({
    template,
    canDelete,
    onDelete,
}: {
    template: EmailTemplateSummary;
    canDelete: boolean;
    onDelete: () => void;
}) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-mr-1.5 size-7 text-muted-foreground"
                    aria-label={`Actions for ${template.name}`}
                >
                    <MoreVertical className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={show([slug, template.id])}>
                        <Pencil /> Open
                    </Link>
                </DropdownMenuItem>
                {canDelete ? (
                    <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 /> Delete
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function FolderMenu({
    folder,
    onRename,
    onDelete,
}: {
    folder: TemplateFolder;
    onRename: () => void;
    onDelete: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-mr-1.5 size-7 text-muted-foreground"
                    aria-label={`Actions for ${folder.name}`}
                >
                    <MoreVertical className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onRename}>
                    <Pencil /> Rename
                </DropdownMenuItem>
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2 /> Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function TemplatesIndex({ templates, folders, can }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState('');
    const [folderModalOpen, setFolderModalOpen] = useState(false);
    const [editingFolder, setEditingFolder] = useState<TemplateFolder | null>(
        null,
    );
    const [deletingFolder, setDeletingFolder] = useState<TemplateFolder | null>(
        null,
    );
    const [deletingTemplate, setDeletingTemplate] =
        useState<EmailTemplateSummary | null>(null);

    const query = search.trim().toLowerCase();
    const hasQuery = query !== '';

    const groups = useMemo<Group[]>(() => {
        const matches = (template: EmailTemplateSummary) =>
            query === '' ||
            template.name.toLowerCase().includes(query) ||
            (template.subject ?? '').toLowerCase().includes(query);

        const byFolder: Group[] = folders.map((folder) => ({
            key: `folder-${folder.id}`,
            folder,
            templates: templates.filter(
                (template) =>
                    template.folder_id === folder.id && matches(template),
            ),
        }));

        const unfiled = templates.filter(
            (template) => template.folder_id === null && matches(template),
        );

        if (unfiled.length > 0) {
            byFolder.push({ key: 'unfiled', folder: null, templates: unfiled });
        }

        return hasQuery
            ? byFolder.filter(
                  (group) =>
                      group.folder === null || group.templates.length > 0,
              )
            : byFolder;
    }, [folders, templates, query, hasQuery]);

    const visibleTemplates = groups.reduce(
        (total, group) => total + group.templates.length,
        0,
    );
    // Without a search the count is every folder, including empty ones (07 A's
    // `14 templates · 3 folders`); with a search it is the folders that match.
    const visibleFolders = hasQuery
        ? groups.filter(
              (group) => group.folder !== null && group.templates.length > 0,
          ).length
        : folders.length;

    const openNewFolder = () => {
        setEditingFolder(null);
        setFolderModalOpen(true);
    };

    const openEditFolder = (folder: TemplateFolder) => {
        setEditingFolder(folder);
        setFolderModalOpen(true);
    };

    const newTemplateButton = can.create ? (
        <CreateTemplateModal folders={folders}>
            <Button data-test="templates-new-button" aria-label="New template">
                <Plus />
                <span className="hidden lg:inline">New template</span>
                <span className="lg:hidden">New</span>
            </Button>
        </CreateTemplateModal>
    ) : null;

    return (
        <>
            <Head title="Templates" />

            <div className="flex w-full flex-col gap-5 px-4 py-6 lg:px-6">
                <PageHead
                    title="Templates"
                    description="Organize reusable emails for your campaigns"
                    hideDescriptionOnMobile
                    mobileNav
                    actions={
                        can.create ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={openNewFolder}
                                    aria-label="New folder"
                                    data-test="templates-new-folder-button"
                                    className="max-lg:px-2.5"
                                >
                                    <FolderPlus />
                                    <span className="hidden lg:inline">
                                        New folder
                                    </span>
                                </Button>
                                {newTemplateButton}
                            </>
                        ) : null
                    }
                />

                <div className="flex flex-col gap-3">
                    <form
                        role="search"
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(event) => event.preventDefault()}
                    >
                        <Input
                            type="search"
                            aria-label="Search templates"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search templates…"
                            className="h-8 w-full sm:w-[260px]"
                        />
                        <p className="ms-auto hidden text-label leading-4 text-muted-foreground lg:block">
                            {visibleTemplates}{' '}
                            {visibleTemplates === 1 ? 'template' : 'templates'}{' '}
                            · {visibleFolders}{' '}
                            {visibleFolders === 1 ? 'folder' : 'folders'}
                        </p>
                    </form>

                    {groups.length === 0 ? (
                        hasQuery ? (
                            <EmptyState
                                icon={FileText}
                                title="No templates match your search"
                                description="Try a different name or subject."
                                actions={
                                    <Button
                                        variant="outline"
                                        onClick={() => setSearch('')}
                                    >
                                        Clear search
                                    </Button>
                                }
                            />
                        ) : (
                            <EmptyState
                                icon={FileText}
                                title="No templates yet"
                                description="Create a template to reuse your best emails."
                                actions={newTemplateButton}
                            />
                        )
                    ) : (
                        groups.map((group) => {
                            const folder = group.folder;

                            return (
                                <section
                                    key={group.key}
                                    aria-label={
                                        folder?.name ??
                                        'Templates with no folder'
                                    }
                                    className="overflow-hidden rounded-lg border bg-card"
                                    data-test="template-group"
                                >
                                    <div className="flex h-11 items-center gap-2 border-b bg-muted/50 px-4 lg:h-[39px]">
                                        <FolderClosed
                                            className="size-4 shrink-0 text-primary"
                                            aria-hidden
                                        />
                                        <span className="truncate text-body leading-5 font-medium">
                                            {folder?.name ?? 'No folder'}
                                        </span>
                                        <span className="text-label leading-4 text-muted-foreground">
                                            {group.templates.length}
                                        </span>
                                        {can.create && folder ? (
                                            <div className="ms-auto -mr-1.5 flex items-center">
                                                <FolderMenu
                                                    folder={folder}
                                                    onRename={() =>
                                                        openEditFolder(folder)
                                                    }
                                                    onDelete={() =>
                                                        setDeletingFolder(
                                                            folder,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                    </div>

                                    {group.templates.length === 0 ? (
                                        <p className="px-4 py-2.5 text-body leading-5 text-muted-foreground">
                                            No templates in this folder yet.
                                        </p>
                                    ) : (
                                        group.templates.map((template) => (
                                            <div
                                                key={template.id}
                                                className="group flex min-h-[55px] min-w-0 items-center gap-2 border-b px-4 py-0.5 last:border-b-0 hover:bg-accent/40 lg:min-h-[39px] lg:py-0"
                                                data-test="template-row"
                                            >
                                                <FileText
                                                    className="size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden
                                                />
                                                <Link
                                                    href={show([
                                                        slug,
                                                        template.id,
                                                    ])}
                                                    className="flex min-w-0 flex-1 flex-col justify-center self-stretch rounded-sm text-body leading-5 font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary-border lg:flex-row lg:items-center"
                                                    title={template.name}
                                                >
                                                    <span className="truncate">
                                                        {template.name}
                                                    </span>
                                                    {updatedLabel(
                                                        template.updated_at,
                                                    ) ? (
                                                        <span className="text-label leading-4 text-muted-foreground lg:hidden">
                                                            {updatedLabel(
                                                                template.updated_at,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </Link>
                                                <div className="flex shrink-0 items-center gap-3">
                                                    {template.is_draft ? (
                                                        <Badge
                                                            variant="neutral"
                                                            className="lg:mr-6"
                                                        >
                                                            Draft
                                                        </Badge>
                                                    ) : null}
                                                    {updatedLabel(
                                                        template.updated_at,
                                                    ) ? (
                                                        <span className="hidden text-label leading-4 text-muted-foreground lg:inline">
                                                            {updatedLabel(
                                                                template.updated_at,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                    {can.create ? (
                                                        <RowMenu
                                                            template={template}
                                                            canDelete={
                                                                can.create
                                                            }
                                                            onDelete={() =>
                                                                setDeletingTemplate(
                                                                    template,
                                                                )
                                                            }
                                                        />
                                                    ) : null}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </section>
                            );
                        })
                    )}
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
    showHeader: false,
});

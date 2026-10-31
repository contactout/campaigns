import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Italic,
    List,
    ListOrdered,
    Redo2,
    Strikethrough,
    Undo2,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    value: string;
    onChange?: (html: string) => void;
    labelledBy?: string;
    readOnly?: boolean;
    /**
     * `icons` is the original icon toolbar used by the template screens.
     * `board` is the labelled toolbar on the sequence-step board
     * (`B I U • 1. Link`) with a slot for extra actions on the right.
     */
    toolbar?: 'icons' | 'board';
    actions?: ReactNode;
    minHeightClass?: string;
};

export default function RichTextEditor({
    name,
    value,
    onChange,
    labelledBy,
    readOnly = false,
    toolbar = 'icons',
    actions,
    minHeightClass = 'min-h-72',
}: Props) {
    const [html, setHtml] = useState(value);
    const [linkOpen, setLinkOpen] = useState(false);
    const [linkHref, setLinkHref] = useState('');

    const editor = useEditor({
        extensions: [StarterKit],
        content: value,
        editable: !readOnly,
        immediatelyRender: false,
        onUpdate: ({ editor }) => {
            const next = editor.getHTML();
            setHtml(next);
            onChange?.(next);
        },
        editorProps: {
            attributes: {
                'aria-labelledby': labelledBy ?? '',
                class: cn(
                    minHeightClass,
                    'max-w-none px-3 py-2 focus:outline-none [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6',
                    // The template screens keep their pre-phase-3 scale; the
                    // board toolbar is the 13px body role.
                    toolbar === 'board'
                        ? 'text-body leading-5 [&_h2]:text-h3'
                        : 'text-sm [&_h2]:text-lg',
                ),
            },
        },
    });

    useEffect(() => {
        editor?.setEditable(!readOnly);
    }, [editor, readOnly]);

    useEffect(() => {
        if (!editor) {
            return;
        }

        if (value !== editor.getHTML()) {
            editor.commands.setContent(value, { emitUpdate: false });
            setHtml(value);
        }
    }, [editor, value]);

    const applyLink = () => {
        const href = linkHref.trim();

        if (href !== '') {
            editor
                ?.chain()
                .focus()
                .extendMarkRange('link')
                .setLink({ href })
                .run();
        }

        setLinkHref('');
        setLinkOpen(false);
    };

    const actionsList: Array<{
        icon: typeof Bold;
        label: string;
        run: () => void;
        isActive: () => boolean;
    }> = [
        {
            icon: Bold,
            label: 'Bold',
            run: () => editor?.chain().focus().toggleBold().run(),
            isActive: () => editor?.isActive('bold') ?? false,
        },
        {
            icon: Italic,
            label: 'Italic',
            run: () => editor?.chain().focus().toggleItalic().run(),
            isActive: () => editor?.isActive('italic') ?? false,
        },
        {
            icon: Strikethrough,
            label: 'Strikethrough',
            run: () => editor?.chain().focus().toggleStrike().run(),
            isActive: () => editor?.isActive('strike') ?? false,
        },
        {
            icon: List,
            label: 'Bullet list',
            run: () => editor?.chain().focus().toggleBulletList().run(),
            isActive: () => editor?.isActive('bulletList') ?? false,
        },
        {
            icon: ListOrdered,
            label: 'Numbered list',
            run: () => editor?.chain().focus().toggleOrderedList().run(),
            isActive: () => editor?.isActive('orderedList') ?? false,
        },
        {
            icon: Undo2,
            label: 'Undo',
            run: () => editor?.chain().focus().undo().run(),
            isActive: () => false,
        },
        {
            icon: Redo2,
            label: 'Redo',
            run: () => editor?.chain().focus().redo().run(),
            isActive: () => false,
        },
    ];

    const boardActions: Array<{
        label: string;
        glyph: string;
        aria: string;
        run: () => void;
        isActive: () => boolean;
        className?: string;
    }> = [
        {
            label: 'Bold',
            glyph: 'B',
            aria: 'Bold',
            run: () => editor?.chain().focus().toggleBold().run(),
            isActive: () => editor?.isActive('bold') ?? false,
            className: 'font-bold',
        },
        {
            label: 'Italic',
            glyph: 'I',
            aria: 'Italic',
            run: () => editor?.chain().focus().toggleItalic().run(),
            isActive: () => editor?.isActive('italic') ?? false,
            className: 'italic',
        },
        {
            label: 'Underline',
            glyph: 'U',
            aria: 'Underline',
            run: () => editor?.chain().focus().toggleUnderline().run(),
            isActive: () => editor?.isActive('underline') ?? false,
            className: 'underline',
        },
        {
            label: 'Bullet list',
            glyph: '•',
            aria: 'Bullet list',
            run: () => editor?.chain().focus().toggleBulletList().run(),
            isActive: () => editor?.isActive('bulletList') ?? false,
        },
        {
            label: 'Numbered list',
            glyph: '1.',
            aria: 'Numbered list',
            run: () => editor?.chain().focus().toggleOrderedList().run(),
            isActive: () => editor?.isActive('orderedList') ?? false,
        },
        {
            label: 'Link',
            glyph: 'Link',
            aria: 'Link',
            run: () => setLinkOpen((open) => !open),
            isActive: () => editor?.isActive('link') ?? false,
        },
    ];

    return (
        <div className="rounded-md border bg-background focus-within:ring-2 focus-within:ring-primary-border">
            {!readOnly ? (
                toolbar === 'board' ? (
                    <div className="flex flex-wrap items-center gap-1 border-b px-1.5 py-1">
                        {boardActions.map((action, index) => (
                            <span
                                key={action.label}
                                className="flex items-center"
                            >
                                {index === 3 || index === 5 ? (
                                    <span
                                        className="mx-1 h-4 w-px bg-border"
                                        aria-hidden
                                    />
                                ) : null}
                                <Button
                                    type="button"
                                    variant={
                                        action.isActive()
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    size="sm"
                                    className={cn('px-2', action.className)}
                                    aria-label={action.aria}
                                    aria-pressed={action.isActive()}
                                    onClick={action.run}
                                >
                                    {action.glyph}
                                </Button>
                            </span>
                        ))}
                        {actions ? (
                            <div className="ms-auto flex items-center gap-1.5">
                                {actions}
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center gap-1 border-b p-1">
                        {actionsList.map((action) => (
                            <Button
                                key={action.label}
                                type="button"
                                variant={
                                    action.isActive() ? 'secondary' : 'ghost'
                                }
                                size="icon"
                                aria-label={action.label}
                                onClick={action.run}
                            >
                                <action.icon className="h-4 w-4" />
                            </Button>
                        ))}
                    </div>
                )
            ) : null}

            {linkOpen ? (
                <div className="flex items-center gap-1.5 border-b px-1.5 py-1.5">
                    <Input
                        autoFocus
                        value={linkHref}
                        onChange={(event) => setLinkHref(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                applyLink();
                            }

                            if (event.key === 'Escape') {
                                event.stopPropagation();
                                setLinkOpen(false);
                            }
                        }}
                        placeholder="https://example.com"
                        aria-label="Link URL"
                        className="h-7"
                    />
                    <Button type="button" size="sm" onClick={applyLink}>
                        Apply
                    </Button>
                </div>
            ) : null}

            <EditorContent editor={editor} />

            <input type="hidden" name={name} value={html} />
        </div>
    );
}

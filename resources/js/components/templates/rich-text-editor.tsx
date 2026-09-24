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
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    name: string;
    value: string;
};

export default function RichTextEditor({ name, value }: Props) {
    const [html, setHtml] = useState(value);

    const editor = useEditor({
        extensions: [StarterKit],
        content: value,
        immediatelyRender: false,
        onUpdate: ({ editor }) => setHtml(editor.getHTML()),
        editorProps: {
            attributes: {
                class: 'min-h-72 max-w-none px-3 py-2 text-sm focus:outline-none [&_h2]:text-lg [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6',
            },
        },
    });

    const actions: Array<{
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

    return (
        <div className="rounded-md border">
            <div className="flex flex-wrap items-center gap-1 border-b p-1">
                {actions.map((action) => (
                    <Button
                        key={action.label}
                        type="button"
                        variant={action.isActive() ? 'secondary' : 'ghost'}
                        size="icon"
                        aria-label={action.label}
                        onClick={action.run}
                    >
                        <action.icon className="h-4 w-4" />
                    </Button>
                ))}
            </div>

            <EditorContent editor={editor} />

            <input type="hidden" name={name} value={html} />
        </div>
    );
}

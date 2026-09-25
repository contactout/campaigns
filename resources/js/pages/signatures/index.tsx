import { Head } from '@inertiajs/react';
import { Pencil, PenLine, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import DeleteSignatureModal from '@/components/signatures/delete-signature-modal';
import SignatureFormModal from '@/components/signatures/signature-form-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/signatures';
import type { Signature } from '@/types';

type Props = {
    signatures: Signature[];
    can: {
        create: boolean;
    };
};

export default function SignaturesIndex({ signatures, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Signature | null>(null);
    const [deleting, setDeleting] = useState<Signature | null>(null);

    const openNew = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (signature: Signature) => {
        setEditing(signature);
        setFormOpen(true);
    };

    return (
        <>
            <Head title="Signatures" />

            <h1 className="sr-only">Signatures</h1>

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Signatures"
                        description="Appended to the emails you send"
                    />

                    {can.create ? (
                        <Button onClick={openNew} data-test="signatures-new">
                            <Plus /> New signature
                        </Button>
                    ) : null}
                </div>

                {signatures.length === 0 ? (
                    <EmptyState
                        className="min-h-64 py-12"
                        icon={PenLine}
                        title="No signatures yet"
                        description="Create a signature to close your emails consistently."
                        actions={
                            can.create ? (
                                <Button onClick={openNew}>
                                    <Plus /> New signature
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-3">
                        {signatures.map((signature) => (
                            <div
                                key={signature.id}
                                className="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-5 sm:flex-nowrap sm:p-6"
                                data-test="signature-row"
                            >
                                <div className="min-w-0 space-y-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">
                                            {signature.name}
                                        </span>
                                        {signature.is_default ? (
                                            <Badge variant="secondary">
                                                Default
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="max-w-2xl border-l-2 pl-3 text-sm leading-relaxed break-words whitespace-pre-line text-muted-foreground">
                                        {signature.body}
                                    </p>
                                </div>

                                {can.create ? (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${signature.name}`}
                                            onClick={() => openEdit(signature)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Delete ${signature.name}`}
                                            onClick={() =>
                                                setDeleting(signature)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {formOpen ? (
                <SignatureFormModal
                    signature={editing}
                    open={formOpen}
                    onOpenChange={setFormOpen}
                />
            ) : null}

            {deleting ? (
                <DeleteSignatureModal
                    signature={deleting}
                    open={deleting !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeleting(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}

SignaturesIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Signatures',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

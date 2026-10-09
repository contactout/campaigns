import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import RichTextEditor from '@/components/templates/rich-text-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/campaigns/steps';
import type {
    CampaignSignatureOption,
    CampaignStep,
    CampaignTemplateOption,
    MergePlaceholder,
} from '@/types';

const SENDER_SIGNATURE = 'sender';

type Props = {
    campaignId: number;
    step?: CampaignStep | null;
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function StepFormModal({
    campaignId,
    step,
    templates,
    signatures,
    placeholders,
    open,
    onOpenChange,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [subject, setSubject] = useState(step?.subject ?? '');
    const [body, setBody] = useState(step?.body ?? '');

    const formProps = step
        ? update.form([slug, campaignId, step.id])
        : store.form([slug, campaignId]);

    const applyTemplate = (templateId: string) => {
        const template = templates.find(
            (item) => String(item.id) === templateId,
        );

        if (!template) {
            return;
        }

        setSubject(template.subject);
        setBody(template.body);
    };

    const insertSignature = (signatureId: string) => {
        if (signatureId === SENDER_SIGNATURE) {
            setBody((current) => `${current}<p>{{signature}}</p>`);

            return;
        }

        const signature = signatures.find(
            (item) => String(item.id) === signatureId,
        );

        if (!signature) {
            return;
        }

        setBody((current) => `${current}${signature.body}`);
    };

    const insertPlaceholder = (name: string) => {
        if (!name) {
            return;
        }

        setBody((current) => `${current}<p>{{${name}}}</p>`);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <Form
                    key={`${String(open)}-${step?.id ?? 'new'}`}
                    {...formProps}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {step ? 'Edit step' : 'New step'}
                                </DialogTitle>
                                <DialogDescription>
                                    Email steps run in order.
                                </DialogDescription>
                            </DialogHeader>

                            {templates.length > 0 ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="apply-template">
                                        Apply template
                                    </Label>
                                    <select
                                        id="apply-template"
                                        value=""
                                        onChange={(event) =>
                                            applyTemplate(event.target.value)
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="">
                                            Choose a template...
                                        </option>
                                        {templates.map((template) => (
                                            <option
                                                key={template.id}
                                                value={template.id}
                                            >
                                                {template.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="subject">Subject</Label>
                                <Input
                                    id="subject"
                                    name="subject"
                                    value={subject}
                                    onChange={(event) =>
                                        setSubject(event.target.value)
                                    }
                                    placeholder="Quick question"
                                    required
                                />
                                <InputError message={errors.subject} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Label>Body</Label>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {placeholders.length > 0 ? (
                                            <select
                                                value=""
                                                aria-label="Insert placeholder"
                                                onChange={(event) =>
                                                    insertPlaceholder(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-xs"
                                            >
                                                <option value="">
                                                    Insert placeholder
                                                </option>
                                                {placeholders.map(
                                                    (placeholder) => (
                                                        <option
                                                            key={
                                                                placeholder.name
                                                            }
                                                            value={
                                                                placeholder.name
                                                            }
                                                        >
                                                            {placeholder.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        ) : null}

                                        <select
                                            value=""
                                            aria-label="Insert signature"
                                            onChange={(event) =>
                                                insertSignature(
                                                    event.target.value,
                                                )
                                            }
                                            className="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-xs"
                                        >
                                            <option value="">
                                                Insert signature
                                            </option>
                                            <option value={SENDER_SIGNATURE}>
                                                Sender's signature
                                            </option>
                                            {signatures.length > 0 ? (
                                                <optgroup label="Paste a fixed signature">
                                                    {signatures.map(
                                                        (signature) => (
                                                            <option
                                                                key={
                                                                    signature.id
                                                                }
                                                                value={
                                                                    signature.id
                                                                }
                                                            >
                                                                {signature.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </optgroup>
                                            ) : null}
                                        </select>
                                    </div>
                                </div>
                                <RichTextEditor
                                    name="body"
                                    value={body}
                                    onChange={setBody}
                                />
                                <InputError message={errors.body} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="day">
                                        Day (from campaign start)
                                    </Label>
                                    <Input
                                        id="day"
                                        name="day"
                                        type="number"
                                        min={0}
                                        max={365}
                                        defaultValue={step?.day ?? 0}
                                        required
                                    />
                                    <InputError message={errors.day} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="time">
                                        Time (optional)
                                    </Label>
                                    <Input
                                        id="time"
                                        name="time"
                                        type="time"
                                        defaultValue={
                                            step?.time
                                                ? step.time.slice(0, 5)
                                                : ''
                                        }
                                    />
                                    <InputError message={errors.time} />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="is_threaded"
                                    value="1"
                                    defaultChecked={step?.is_threaded}
                                />
                                Send as a reply in the same thread
                            </label>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {step ? 'Save step' : 'Add step'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

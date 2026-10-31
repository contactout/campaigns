import { Form, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/templates/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/campaigns/steps';
import type {
    CampaignSignatureOption,
    CampaignStep,
    CampaignTemplateOption,
    MergePlaceholder,
} from '@/types';

const SENDER_SIGNATURE = 'sender';

export type StepFormState = {
    errors: Record<string, string>;
    processing: boolean;
    /** True when any field differs from the step it was opened with. */
    isDirty: boolean;
    /** The subject, body, placeholder, day/time and threading fields. */
    fields: ReactNode;
};

type Props = {
    campaignId: number;
    step?: CampaignStep | null;
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    onSuccess?: () => void;
    formClassName?: string;
    fieldsClassName?: string;
    /** Reports dirtiness so a caller can confirm before discarding a draft. */
    onDirtyChange?: (isDirty: boolean) => void;
    children: (state: StepFormState) => ReactNode;
};

/**
 * The step form itself, shared by the modal and the two-pane sequence editor.
 *
 * The caller owns the chrome (dialog header/footer or editor card header/footer)
 * and receives the field stack, so both layouts submit the same payload with the
 * same template, signature and placeholder insertion.
 */
export default function StepForm({
    campaignId,
    step,
    templates,
    signatures,
    placeholders,
    onSuccess,
    formClassName,
    fieldsClassName = 'flex flex-col gap-4',
    onDirtyChange,
    children,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    const [subject, setSubject] = useState(step?.subject ?? '');
    const [body, setBody] = useState(step?.body ?? '');
    const [day, setDay] = useState(String(step?.day ?? 0));
    const [time, setTime] = useState(step?.time ? step.time.slice(0, 5) : '');
    const [isThreaded, setIsThreaded] = useState(Boolean(step?.is_threaded));
    const [templateId, setTemplateId] = useState('');

    const initial = {
        subject: step?.subject ?? '',
        body: step?.body ?? '',
        day: String(step?.day ?? 0),
        time: step?.time ? step.time.slice(0, 5) : '',
        threaded: Boolean(step?.is_threaded),
    };

    const isDirty =
        subject !== initial.subject ||
        body !== initial.body ||
        day !== initial.day ||
        time !== initial.time ||
        isThreaded !== initial.threaded;

    useEffect(() => {
        onDirtyChange?.(isDirty);
    }, [isDirty, onDirtyChange]);

    const formProps = step
        ? update.form([slug, campaignId, step.id])
        : store.form([slug, campaignId]);

    const applyTemplate = (id: string) => {
        const template = templates.find((item) => String(item.id) === id);

        // Clear the selection afterwards: a Radix Select fires nothing when the
        // same item is picked twice, and re-applying a template is a real case
        // (edit the subject, then put the template back).
        setTemplateId('');

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
        <Form {...formProps} className={formClassName} onSuccess={onSuccess}>
            {({ errors, processing }) => (
                <>
                    {children({
                        errors,
                        processing,
                        isDirty,
                        fields: (
                            <div className={fieldsClassName}>
                                {templates.length > 0 ? (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="apply-template">
                                            Apply template
                                        </Label>
                                        <Select
                                            value={templateId}
                                            onValueChange={applyTemplate}
                                        >
                                            <SelectTrigger
                                                id="apply-template"
                                                className="w-full"
                                                aria-label="Apply template"
                                            >
                                                <SelectValue placeholder="Choose a template…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {templates.map((template) => (
                                                    <SelectItem
                                                        key={template.id}
                                                        value={String(
                                                            template.id,
                                                        )}
                                                    >
                                                        {template.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ) : null}

                                <div className="grid gap-1.5">
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

                                <div className="grid gap-1.5">
                                    <Label id="step-body-label">Body</Label>
                                    <RichTextEditor
                                        name="body"
                                        value={body}
                                        onChange={setBody}
                                        labelledBy="step-body-label"
                                        toolbar="board"
                                        minHeightClass="min-h-[220px]"
                                        actions={
                                            <>
                                                {placeholders.length > 0 ? (
                                                    <PlaceholderMenu
                                                        placeholders={
                                                            placeholders
                                                        }
                                                        onInsert={
                                                            insertPlaceholder
                                                        }
                                                    />
                                                ) : null}
                                                <SignatureMenu
                                                    signatures={signatures}
                                                    onInsert={insertSignature}
                                                />
                                            </>
                                        }
                                    />
                                    <InputError message={errors.body} />
                                </div>

                                {placeholders.length > 0 ? (
                                    <div className="grid gap-1.5">
                                        <p className="text-label leading-4 font-medium text-muted-foreground">
                                            Placeholders
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {placeholders.map((placeholder) => (
                                                <button
                                                    key={placeholder.name}
                                                    type="button"
                                                    onClick={() =>
                                                        insertPlaceholder(
                                                            placeholder.name,
                                                        )
                                                    }
                                                    className="rounded-md border border-badge-info-border bg-accent px-1.5 py-0.5 font-mono text-label leading-4 text-primary outline-none focus-visible:ring-2 focus-visible:ring-primary-border"
                                                    aria-label={`Insert ${placeholder.label}`}
                                                >
                                                    {`{{${placeholder.name}}}`}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ) : null}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="day">
                                            Day (from campaign start)
                                        </Label>
                                        <Input
                                            id="day"
                                            name="day"
                                            type="number"
                                            min={0}
                                            max={365}
                                            value={day}
                                            onChange={(event) =>
                                                setDay(event.target.value)
                                            }
                                            required
                                        />
                                        <InputError message={errors.day} />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="time">
                                            Time (optional)
                                        </Label>
                                        <Input
                                            id="time"
                                            name="time"
                                            type="time"
                                            value={time}
                                            onChange={(event) =>
                                                setTime(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.time} />
                                    </div>
                                </div>

                                <label className="flex items-center gap-2 text-body leading-5">
                                    <Checkbox
                                        name="is_threaded"
                                        value="1"
                                        checked={isThreaded}
                                        onCheckedChange={(checked) =>
                                            setIsThreaded(checked === true)
                                        }
                                    />
                                    Send as a reply in the same thread
                                </label>
                            </div>
                        ),
                    })}
                </>
            )}
        </Form>
    );
}

function PlaceholderMenu({
    placeholders,
    onInsert,
}: {
    placeholders: MergePlaceholder[];
    onInsert: (name: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    + Placeholder
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {placeholders.map((placeholder) => (
                    <DropdownMenuItem
                        key={placeholder.name}
                        onSelect={() => onInsert(placeholder.name)}
                    >
                        {placeholder.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SignatureMenu({
    signatures,
    onInsert,
}: {
    signatures: CampaignSignatureOption[];
    onInsert: (signatureId: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    Signature
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={() => onInsert(SENDER_SIGNATURE)}>
                    Sender&apos;s signature
                </DropdownMenuItem>
                {signatures.map((signature) => (
                    <DropdownMenuItem
                        key={signature.id}
                        onSelect={() => onInsert(String(signature.id))}
                    >
                        {signature.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

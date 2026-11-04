import { Form, usePage } from '@inertiajs/react';
import { PenLine, Plus } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import FormLabel from '@/components/form-label';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { signatureHtml } from '@/lib/signature';
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
    // Inserts text at the editor's cursor, as a literal text node, so a field
    // name such as `Price <USD>` is never parsed as HTML.
    const insertRef = useRef<((text: string) => void) | null>(null);
    // What the editor actually holds for the saved body. TipTap normalises the
    // stored HTML on open (`<br />` -> `<br>`), so comparing against the raw
    // prop would report every freshly opened step as edited and prompt to
    // discard changes the user never made.
    const [savedBody, setSavedBody] = useState(step?.body ?? '');
    const [day, setDay] = useState(String(step?.day ?? 0));
    const [time, setTime] = useState(step?.time ? step.time.slice(0, 5) : '');
    const [isThreaded, setIsThreaded] = useState(Boolean(step?.is_threaded));
    const [templateId, setTemplateId] = useState('');

    const initial = {
        subject: step?.subject ?? '',
        body: savedBody,
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

        setBody((current) => `${current}${signatureHtml(signature.body)}`);
    };

    const insertPlaceholder = (name: string) => {
        if (!name) {
            return;
        }

        insertRef.current?.(`{{${name}}}`);
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
                                        <FormLabel htmlFor="apply-template">
                                            Apply template
                                        </FormLabel>
                                        <Select
                                            // Controlled with the reset value: an
                                            // uncontrolled Select keeps its last pick and
                                            // cannot re-apply the same template. Radix
                                            // shows the placeholder for an empty value.
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
                                    <FormLabel htmlFor="subject">
                                        Subject
                                    </FormLabel>
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
                                    <FormLabel id="step-body-label">
                                        Body
                                    </FormLabel>
                                    <RichTextEditor
                                        name="body"
                                        value={body}
                                        onChange={setBody}
                                        insertRef={insertRef}
                                        onCanonical={(html) => {
                                            setSavedBody(html);
                                            setBody(html);
                                        }}
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
                                                    className="inline-flex items-center rounded-md border border-badge-info-border bg-accent px-1.5 py-0.5 font-mono text-label leading-4 text-primary outline-none focus-visible:ring-2 focus-visible:ring-primary-border max-lg:min-h-11"
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
                                        <FormLabel htmlFor="day">Day</FormLabel>
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
                                            aria-describedby="day-hint"
                                            required
                                        />
                                        <p
                                            id="day-hint"
                                            className="text-xs text-muted-foreground"
                                        >
                                            From campaign start, or from when
                                            the recipient joins if later.
                                        </p>
                                        <InputError message={errors.day} />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <FormLabel htmlFor="time">
                                            Time (optional)
                                        </FormLabel>
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

                                {/* Native label activation forwards the click to the
                                    checkbox, so the label must not toggle it as well. */}
                                <label className="flex cursor-pointer items-center gap-2 text-body leading-5 max-lg:min-h-11">
                                    <span className="flex shrink-0 items-center justify-center max-lg:size-11">
                                        <Checkbox
                                            name="is_threaded"
                                            value="1"
                                            checked={isThreaded}
                                            onCheckedChange={(checked) =>
                                                setIsThreaded(checked === true)
                                            }
                                        />
                                    </span>
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
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label="Insert placeholder"
                >
                    <Plus className="lg:hidden" />
                    <span className="lg:hidden">Insert</span>
                    <span className="hidden lg:inline">+ Placeholder</span>
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
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label="Insert signature"
                >
                    <PenLine className="lg:hidden" />
                    <span className="hidden lg:inline">Signature</span>
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

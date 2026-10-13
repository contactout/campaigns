import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/contacts/imports';
import type { ContactField, ListOption } from '@/types';

type Props = {
    fields: ContactField[];
    lists: ListOption[];
    defaultListId?: number | null;
    children: React.ReactNode;
};

type Step = 'upload' | 'map' | 'options';

const IGNORE = 'ignore';
const NO_LIST = 'none';
const PREVIEW_BYTES = 64 * 1024;

/**
 * Parse up to `limit` non-blank records from the start of a CSV string.
 */
function parseCsvRecords(text: string, limit: number): string[][] {
    const records: string[][] = [];
    let record: string[] = [];
    let value = '';
    let quoted = false;

    const endRecord = () => {
        record.push(value.trim());
        value = '';

        if (record.some((cell) => cell !== '')) {
            records.push(record);
        }

        record = [];
    };

    for (let i = 0; i < text.length && records.length < limit; i++) {
        const char = text[i];

        if (quoted) {
            if (char === '"' && text[i + 1] === '"') {
                value += '"';
                i++;
            } else if (char === '"') {
                quoted = false;
            } else {
                value += char;
            }
        } else if (char === '"') {
            quoted = true;
        } else if (char === ',') {
            record.push(value.trim());
            value = '';
        } else if (char === '\n' || char === '\r') {
            if (char === '\r' && text[i + 1] === '\n') {
                i++;
            }

            endRecord();
        } else {
            value += char;
        }
    }

    if (records.length < limit && (value !== '' || record.length > 0)) {
        endRecord();
    }

    return records;
}

function guessTarget(header: string, fields: ContactField[]): string {
    const normalized = header.trim().toLowerCase();

    if (['email', 'e-mail', 'email address'].includes(normalized)) {
        return 'email';
    }

    if (['name', 'full name', 'contact name'].includes(normalized)) {
        return 'name';
    }

    const field = fields.find(
        (candidate) => candidate.name.toLowerCase() === normalized,
    );

    return field ? `field:${field.id}` : IGNORE;
}

export default function ImportContactsModal({
    fields,
    lists,
    defaultListId = null,
    children,
}: Props) {
    const { currentTeam } = usePage().props;
    const [open, setOpen] = useState(false);
    const [step, setStep] = useState<Step>('upload');
    const [file, setFile] = useState<File | null>(null);
    const [headers, setHeaders] = useState<string[]>([]);
    const [sample, setSample] = useState<string[]>([]);
    const [columns, setColumns] = useState<string[]>([]);
    const [duplicateAction, setDuplicateAction] = useState('skip');
    const [listId, setListId] = useState(
        defaultListId ? String(defaultListId) : NO_LIST,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const emailColumns = columns.filter((target) => target === 'email').length;

    const reset = () => {
        setStep('upload');
        setFile(null);
        setHeaders([]);
        setSample([]);
        setColumns([]);
        setDuplicateAction('skip');
        setListId(defaultListId ? String(defaultListId) : NO_LIST);
        setErrors({});
    };

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            reset();
        }
    };

    const handleFile = async (selected: File | null) => {
        setErrors({});
        setFile(selected);
        setHeaders([]);

        if (!selected) {
            return;
        }

        const text = (await selected.slice(0, PREVIEW_BYTES).text()).replace(
            /^\uFEFF/,
            '',
        );
        const [header = [], firstRow = []] = parseCsvRecords(text, 2);

        if (header.length === 0) {
            setErrors({ file: 'The file has no header row.' });

            return;
        }

        setHeaders(header);
        setSample(firstRow);
        setColumns(header.map((name) => guessTarget(name, fields)));
    };

    const setColumn = (index: number, target: string) => {
        setColumns((current) =>
            current.map((value, position) =>
                position === index ? target : value,
            ),
        );
    };

    const submit = () => {
        if (!file) {
            return;
        }

        router.post(
            store.url(currentTeam?.slug ?? ''),
            {
                file,
                columns: columns.map((target) =>
                    target === IGNORE ? '' : target,
                ),
                duplicate_action: duplicateAction,
                list_id: listId === NO_LIST ? '' : listId,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => handleOpenChange(false),
                onError: (nextErrors) => setErrors(nextErrors),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Import contacts</DialogTitle>
                    <DialogDescription>
                        {step === 'upload' &&
                            'Upload a CSV file with a header row.'}
                        {step === 'map' &&
                            'Choose which contact property each column fills.'}
                        {step === 'options' &&
                            'Decide how duplicates are handled and where contacts go.'}
                    </DialogDescription>
                </DialogHeader>

                {step === 'upload' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="import-file">CSV file</Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(event) =>
                                void handleFile(event.target.files?.[0] ?? null)
                            }
                        />
                        <InputError message={errors.file} />
                    </div>
                ) : null}

                {step === 'map' ? (
                    <div className="grid max-h-[50vh] gap-3 overflow-y-auto pr-1">
                        {headers.map((header, index) => (
                            <div
                                key={index}
                                className="grid items-center gap-2 sm:grid-cols-2"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {header || `Column ${index + 1}`}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {sample[index] || '—'}
                                    </p>
                                </div>
                                <Select
                                    value={columns[index]}
                                    onValueChange={(target) =>
                                        setColumn(index, target)
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        aria-label={`Map ${header || `column ${index + 1}`}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={IGNORE}>
                                            Don't import
                                        </SelectItem>
                                        <SelectItem value="name">
                                            Name
                                        </SelectItem>
                                        <SelectItem value="email">
                                            Email
                                        </SelectItem>
                                        {fields.map((field) => (
                                            <SelectItem
                                                key={field.id}
                                                value={`field:${field.id}`}
                                            >
                                                {field.name}
                                            </SelectItem>
                                        ))}
                                        {header ? (
                                            <SelectItem value="new">
                                                Create field “{header}”
                                            </SelectItem>
                                        ) : null}
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                        {emailColumns !== 1 ? (
                            <p className="text-sm text-muted-foreground">
                                Map exactly one column to Email to continue.
                            </p>
                        ) : null}
                        <InputError message={errors.columns} />
                    </div>
                ) : null}

                {step === 'options' ? (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="import-duplicates">
                                Existing contacts
                            </Label>
                            <Select
                                value={duplicateAction}
                                onValueChange={setDuplicateAction}
                            >
                                <SelectTrigger
                                    id="import-duplicates"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="skip">
                                        Skip contacts that already exist
                                    </SelectItem>
                                    <SelectItem value="update">
                                        Update contacts that already exist
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Matched by email. Blank cells never overwrite
                                existing values. Unsubscribed addresses are
                                always skipped.
                            </p>
                            <InputError message={errors.duplicate_action} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="import-list">Add to list</Label>
                            <Select value={listId} onValueChange={setListId}>
                                <SelectTrigger
                                    id="import-list"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_LIST}>
                                        No list
                                    </SelectItem>
                                    {lists.map((list) => (
                                        <SelectItem
                                            key={list.id}
                                            value={String(list.id)}
                                        >
                                            {list.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.list_id} />
                        </div>

                        <InputError message={errors.file ?? errors.columns} />
                    </div>
                ) : null}

                <DialogFooter className="gap-2">
                    {step === 'upload' ? (
                        <Button
                            variant="secondary"
                            onClick={() => handleOpenChange(false)}
                        >
                            Cancel
                        </Button>
                    ) : (
                        <Button
                            variant="secondary"
                            onClick={() =>
                                setStep(step === 'options' ? 'map' : 'upload')
                            }
                        >
                            Back
                        </Button>
                    )}

                    {step === 'upload' ? (
                        <Button
                            onClick={() => setStep('map')}
                            disabled={!file || headers.length === 0}
                        >
                            Next
                        </Button>
                    ) : null}

                    {step === 'map' ? (
                        <Button
                            onClick={() => setStep('options')}
                            disabled={emailColumns !== 1}
                        >
                            Next
                        </Button>
                    ) : null}

                    {step === 'options' ? (
                        <Button onClick={submit} disabled={processing}>
                            Start import
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

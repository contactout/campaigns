import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ContactImportSummary } from '@/types';

type Props = {
    contactImport: ContactImportSummary;
};

const DISMISSED_KEY = 'contacts-import-dismissed';
const POLL_INTERVAL = 2000;

export default function ImportSummary({ contactImport }: Props) {
    const [dismissedId, setDismissedId] = useState(() =>
        typeof window === 'undefined'
            ? 0
            : Number(window.localStorage.getItem(DISMISSED_KEY)),
    );

    useEffect(() => {
        if (contactImport.finished) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['latestImport', 'contacts', 'lists'] });
        }, POLL_INTERVAL);

        return () => window.clearInterval(timer);
    }, [contactImport.finished, contactImport.id]);

    if (dismissedId === contactImport.id) {
        return null;
    }

    const dismiss = () => {
        window.localStorage.setItem(DISMISSED_KEY, String(contactImport.id));
        setDismissedId(contactImport.id);
    };

    const counts = [
        { label: 'Created', value: contactImport.created_count },
        { label: 'Updated', value: contactImport.updated_count },
        { label: 'Skipped', value: contactImport.skipped_count },
        { label: 'Invalid', value: contactImport.invalid_count },
    ];

    return (
        <section
            aria-live="polite"
            className="rounded-xl border bg-card p-4 text-sm"
            data-test="contacts-import-summary"
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="font-medium">
                        {contactImport.status === 'failed'
                            ? 'Import failed'
                            : contactImport.finished
                              ? 'Import complete'
                              : 'Importing contacts…'}
                    </h2>
                    <p className="truncate text-muted-foreground">
                        {contactImport.file_name}
                    </p>
                </div>
                {contactImport.finished ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={dismiss}
                        aria-label="Dismiss import summary"
                    >
                        <X />
                    </Button>
                ) : null}
            </div>

            <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                {counts.map((count) => (
                    <div key={count.label} className="flex gap-1.5">
                        <dt className="text-muted-foreground">{count.label}</dt>
                        <dd className="font-medium tabular-nums">
                            {count.value}
                        </dd>
                    </div>
                ))}
            </dl>

            {contactImport.finished && contactImport.failures.length > 0 ? (
                <details className="mt-3">
                    <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                        Skipped and invalid rows
                    </summary>
                    <ul className="mt-2 max-h-40 space-y-1 overflow-y-auto text-muted-foreground">
                        {contactImport.failures.map((failure, index) => (
                            <li key={index}>
                                Row {failure.row}: {failure.reason}
                            </li>
                        ))}
                    </ul>
                </details>
            ) : null}
        </section>
    );
}

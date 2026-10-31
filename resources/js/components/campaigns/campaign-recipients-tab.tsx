import { Link, router, usePage } from '@inertiajs/react';
import { ListChecks, MoreVertical, Plus, Search, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import RecipientStatusBadge from '@/components/campaigns/recipient-status-badge';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { show } from '@/routes/campaigns';
import {
    bulkDestroy,
    destroy as destroyRecipient,
} from '@/routes/campaigns/recipients';
import type { Paginated, RecipientSummary } from '@/types';

type Props = {
    campaignId: number;
    recipients: Paginated<RecipientSummary>;
    search: string | null;
    canUpdate: boolean;
    onAddRecipients: () => void;
};

const previousLabels = ['&laquo; Previous', 'Previous'];
const nextLabels = ['Next &raquo;', 'Next'];

export default function CampaignRecipientsTab({
    campaignId,
    recipients,
    search,
    canUpdate,
    onAddRecipients,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [term, setTerm] = useState(search ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);

    // `preserveState` keeps this component mounted across pagination and search
    // visits, so ids from the previous page would otherwise stay selected and
    // the bulk Remove would act on rows that are no longer on screen.
    useEffect(() => {
        setSelected([]);
    }, [recipients.current_page, search]);

    const pageIds = recipients.data.map((recipient) => recipient.id);
    // Only ids that are on the page can be acted on, so a row removed from the
    // table can never be posted again.
    const selectedOnPage = selected.filter((id) => pageIds.includes(id));
    const allSelected =
        pageIds.length > 0 &&
        pageIds.every((id) => selectedOnPage.includes(id));
    const someSelected = selectedOnPage.length > 0 && !allSelected;
    const previousLink = recipients.links.find((link) =>
        previousLabels.includes(link.label),
    );
    const nextLink = recipients.links.find((link) =>
        nextLabels.includes(link.label),
    );

    const toggleAll = () => {
        setSelected(allSelected ? [] : pageIds);
    };

    const toggleOne = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const searchRecipients = (event: FormEvent) => {
        event.preventDefault();

        router.get(
            show.url([slug, campaignId]),
            term.trim() === '' ? {} : { recipients_q: term.trim() },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const removeSelected = () => {
        router.post(
            bulkDestroy.url([slug, campaignId]),
            { recipients: selectedOnPage },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    };

    return (
        <div className="flex flex-col gap-3">
            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Remove these recipients?"
                description={`Remove ${selectedOnPage.length} ${
                    selectedOnPage.length === 1 ? 'recipient' : 'recipients'
                } from this campaign? Their sent-email history is deleted too, and this cannot be undone.`}
                confirmLabel="Remove recipients"
                onConfirm={() => {
                    setConfirmOpen(false);
                    removeSelected();
                }}
            />

            <div className="flex flex-wrap items-center gap-2">
                {selectedOnPage.length > 0 ? (
                    <div className="hidden items-center gap-3 lg:flex">
                        <p
                            className="text-body leading-5 font-medium"
                            data-test="campaign-recipients-selected-count"
                        >
                            {selectedOnPage.length} selected
                        </p>
                        {canUpdate ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="text-destructive-foreground"
                                onClick={() => setConfirmOpen(true)}
                            >
                                <Trash2 /> Remove
                            </Button>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2 lg:ms-auto lg:flex-none">
                    <form
                        role="search"
                        onSubmit={searchRecipients}
                        className="min-w-0 flex-1 lg:flex-none"
                    >
                        <div className="relative w-full lg:w-[220px]">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                aria-label="Search recipients"
                                value={term}
                                onChange={(event) =>
                                    setTerm(event.target.value)
                                }
                                placeholder="Search recipients…"
                                className="h-8 pl-8"
                            />
                        </div>
                    </form>

                    {canUpdate ? (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onAddRecipients}
                            aria-label="Add recipients"
                            data-test="campaign-add-recipients-button"
                            className="hidden lg:inline-flex"
                        >
                            <Plus /> Add recipients
                        </Button>
                    ) : null}
                </div>
            </div>

            {recipients.data.length === 0 ? (
                <div className="rounded-lg border bg-card">
                    {search ? (
                        <EmptyState
                            className="border-0 py-16"
                            title="No recipients match your search"
                            description="Try a different name or email address."
                            actions={
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setTerm('');
                                        router.get(
                                            show.url([slug, campaignId]),
                                            {},
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        );
                                    }}
                                >
                                    Clear search
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            className="border-0 py-16"
                            icon={ListChecks}
                            title="No recipients yet"
                            description="Add contacts or lists to start this campaign."
                            actions={
                                canUpdate ? (
                                    <Button
                                        variant="secondary"
                                        onClick={onAddRecipients}
                                    >
                                        <Plus /> Add recipients
                                    </Button>
                                ) : null
                            }
                        />
                    )}
                </div>
            ) : (
                <>
                    <ul
                        className={cn(
                            'divide-y overflow-hidden rounded-lg border bg-card lg:hidden',
                            selectedOnPage.length > 0 && 'mb-16',
                        )}
                        data-test="campaign-recipient-list-mobile"
                    >
                        {recipients.data.map((recipient) => {
                            const isSelected = selectedOnPage.includes(
                                recipient.id,
                            );

                            return (
                                <li
                                    key={recipient.id}
                                    className={cn(
                                        'flex min-h-[64px] items-center gap-1 py-2.5 pr-4 pl-1',
                                        isSelected && 'bg-accent',
                                    )}
                                    onClick={() => {
                                        if (canUpdate) {
                                            toggleOne(recipient.id);
                                        }
                                    }}
                                    data-test="campaign-recipient-row-mobile"
                                >
                                    <span
                                        className="flex size-11 shrink-0 items-center justify-center"
                                        onClick={(event) =>
                                            event.stopPropagation()
                                        }
                                    >
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() =>
                                                toggleOne(recipient.id)
                                            }
                                            aria-label={`Select ${recipient.name}`}
                                        />
                                    </span>
                                    <span className="mx-1 min-w-0 flex-1">
                                        <span className="block truncate text-body leading-5 font-semibold">
                                            {recipient.name}
                                        </span>
                                        <span className="block truncate text-label leading-4 text-muted-foreground">
                                            {recipient.email ?? '—'}
                                        </span>
                                    </span>
                                    <RecipientStatusBadge
                                        recipient={recipient}
                                    />
                                </li>
                            );
                        })}
                    </ul>

                    <div className="flex items-center justify-between gap-2 lg:hidden">
                        <p className="text-label leading-4 text-muted-foreground">
                            Showing {recipients.from ?? 0}–{recipients.to ?? 0}{' '}
                            of {recipients.total}
                        </p>
                        <div className="flex items-center gap-2">
                            {previousLink?.url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={previousLink.url}
                                        preserveScroll
                                        preserveState
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Previous
                                </Button>
                            )}
                            {nextLink?.url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={nextLink.url}
                                        preserveScroll
                                        preserveState
                                    >
                                        Next
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="hidden overflow-hidden rounded-lg border bg-card lg:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-[34px] pr-0">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={toggleAll}
                                            aria-label="Select all recipients on this page"
                                        />
                                    </TableHead>
                                    <TableHead className="w-[220px]">
                                        Name
                                    </TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead className="w-[148px]">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-[64px] text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recipients.data.map((recipient) => (
                                    <TableRow
                                        key={recipient.id}
                                        data-state={
                                            selectedOnPage.includes(
                                                recipient.id,
                                            )
                                                ? 'selected'
                                                : undefined
                                        }
                                        data-test="campaign-recipient-row"
                                    >
                                        <TableCell className="h-[44px] pr-0">
                                            <Checkbox
                                                checked={selectedOnPage.includes(
                                                    recipient.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleOne(recipient.id)
                                                }
                                                aria-label={`Select ${recipient.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="h-[44px] truncate font-semibold">
                                            {recipient.name}
                                        </TableCell>
                                        <TableCell className="h-[44px] truncate text-muted-foreground">
                                            {recipient.email ?? '—'}
                                        </TableCell>
                                        <TableCell className="h-[44px]">
                                            <RecipientStatusBadge
                                                recipient={recipient}
                                            />
                                        </TableCell>
                                        <TableCell className="h-[44px] text-right">
                                            {canUpdate ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-muted-foreground"
                                                            aria-label={`Actions for ${recipient.name}`}
                                                        >
                                                            <MoreVertical className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                router.delete(
                                                                    destroyRecipient.url(
                                                                        [
                                                                            slug,
                                                                            campaignId,
                                                                            recipient.id,
                                                                        ],
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                        preserveState: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 /> Remove
                                                            from campaign
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="flex h-[52px] items-center justify-between gap-2 border-t px-4">
                            <p className="text-body leading-5 text-muted-foreground">
                                Showing {recipients.from ?? 0}–
                                {recipients.to ?? 0} of {recipients.total}
                            </p>
                            <div className="flex items-center gap-2">
                                {previousLink?.url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={previousLink.url}
                                            preserveScroll
                                            preserveState
                                        >
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Previous
                                    </Button>
                                )}
                                {nextLink?.url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={nextLink.url}
                                            preserveScroll
                                            preserveState
                                        >
                                            Next
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Next
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {selectedOnPage.length > 0 && canUpdate ? (
                <div
                    className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex min-h-14 items-center gap-3 border-t bg-background px-4 py-2.5 lg:hidden"
                    data-test="campaign-recipients-selection-bar"
                >
                    <p className="text-body leading-5 font-medium">
                        {selectedOnPage.length} selected
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="ms-auto text-destructive-foreground"
                        onClick={() => setConfirmOpen(true)}
                    >
                        <Trash2 /> Remove
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

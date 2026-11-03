import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type SearchFilters = {
    q: string | null;
    [key: string]: string | number | null;
};

export function useDebouncedSearch(
    url: string,
    filters: SearchFilters,
    disabled = false,
) {
    const [search, setSearch] = useState(filters.q ?? '');
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cancelVisit = useRef<(() => void) | null>(null);

    const cancelSearch = useCallback(() => {
        if (timer.current !== null) {
            clearTimeout(timer.current);
            timer.current = null;
        }

        cancelVisit.current?.();
        cancelVisit.current = null;
    }, []);

    // Sync history/filter changes, but cancel older searches before editing so
    // a slow response cannot replace a newer draft in the input.
    useEffect(() => {
        cancelSearch();
        setSearch(filters.q ?? '');
    }, [filters.q, url, cancelSearch]);

    useEffect(() => {
        if (disabled) {
            cancelSearch();
        }
    }, [disabled, cancelSearch]);

    useEffect(() => cancelSearch, [url, cancelSearch]);

    // Pagination, list tabs and other visits must not leave a timer that can
    // navigate back to the filters from before the visit.
    useEffect(() => router.on('before', cancelSearch), [cancelSearch]);

    const visit = (value: string) => {
        cancelSearch();

        if (disabled) {
            return;
        }

        router.get(
            url,
            { ...filters, q: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onCancelToken: (token) => {
                    cancelVisit.current = () => token.cancel();
                },
            },
        );
    };

    const updateSearch = (value: string) => {
        cancelSearch();
        setSearch(value);

        if (!disabled && value.trim() !== (filters.q ?? '')) {
            timer.current = setTimeout(() => visit(value), 300);
        }
    };

    const clearSearch = () => {
        cancelSearch();
        setSearch('');
    };

    return {
        search,
        updateSearch,
        submitSearch: () => visit(search),
        clearSearch,
        cancelSearch,
    };
}

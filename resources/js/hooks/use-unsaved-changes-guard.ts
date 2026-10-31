import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Options = {
    /** True while the page holds edits that a navigation would throw away. */
    isDirty: boolean;
    /**
     * Visits that keep this page's draft, so they are not worth asking about:
     * the editor's own save, and in-page forms that reload the same page.
     * Everything else (the tab bar, the sidebar, a head action that redirects,
     * the back chevron) is guarded.
     */
    allow?: (visit: PendingVisit) => boolean;
};

export type UnsavedChangesGuard = {
    /** True while the confirm dialog is open. */
    pending: boolean;
    /** Leaves: replays the blocked visit, or runs the blocked local action. */
    confirm: () => void;
    /** Stays and keeps the draft. */
    cancel: () => void;
    /** Runs a local action (Cancel, Esc, a step switch) behind the same confirm. */
    request: (action: () => void) => void;
};

/** Marks the history entry this hook pushed, so cleanup only pops its own. */
const HISTORY_MARKER = '__unsavedChangesGuard';

/** The fields of a pending visit worth replaying; the rest are internals. */
const replayOptions = (
    visit: PendingVisit,
): Parameters<typeof router.visit>[1] => ({
    method: visit.method,
    data: visit.data,
    headers: visit.headers,
    replace: visit.replace,
    preserveScroll: visit.preserveScroll,
    preserveState: visit.preserveState,
    preserveUrl: visit.preserveUrl,
    only: visit.only,
    except: visit.except,
    reset: visit.reset,
    errorBag: visit.errorBag,
    forceFormData: visit.forceFormData,
    queryStringArrayFormat: visit.queryStringArrayFormat,
    async: visit.async,
    showProgress: visit.showProgress,
    fresh: visit.fresh,
    viewTransition: visit.viewTransition,
});

/**
 * Keeps a page's unsaved draft from being thrown away by a navigation.
 *
 * Three paths lose a draft and each needs its own mechanism:
 *
 * - **In-app visits** (`<Link>`, `router.visit`, `useForm`/`<Form>` submits,
 *   head actions that redirect) fire a cancelable `inertia:before` event, so
 *   the visit is cancelled here and replayed once the user confirms.
 * - **Browser Back** never fires `before`. Inertia's own `popstate` handler
 *   (`handlePopstateEvent` in `@inertiajs/core` 3.7.1) restores the previous
 *   entry with `preserveState: false`, which remounts the page and takes the
 *   draft with it, and it cannot be intercepted: a capture-phase listener at
 *   `window` runs after Inertia's, and cancelling the event does not stop it.
 *   What *can* be used is the one branch it does not swap on: an entry whose
 *   history state is `null` only has its URL restored. So while the draft is
 *   dirty the current entry is emptied and a copy of it is pushed above: Back
 *   lands on the emptied entry, the page stays mounted, and the dialog opens.
 * - **A closed tab or a hard reload** cannot be intercepted by a dialog, so
 *   the browser's own `beforeunload` confirmation is the guard there.
 */
export function useUnsavedChangesGuard({
    isDirty,
    allow,
}: Options): UnsavedChangesGuard {
    const [pending, setPending] = useState(false);
    const actionRef = useRef<(() => void) | null>(null);
    const dirtyRef = useRef(isDirty);
    const allowRef = useRef(allow);
    const bypassRef = useRef(false);
    const armedRef = useRef(false);
    const rewindingRef = useRef(false);
    const rewindRef = useRef<(() => Promise<void>) | null>(null);
    // The page's own history state, put back above the emptied entry.
    const homeRef = useRef<unknown>(null);
    // Back resets the scroll position it was intercepted at; put it back.
    const scrollRef = useRef({ x: 0, y: 0 });

    // Kept in refs so the listeners below never capture a stale render.
    useEffect(() => {
        allowRef.current = allow;
    });

    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const rememberScroll = () => {
            scrollRef.current = { x: window.scrollX, y: window.scrollY };
        };
        rememberScroll();
        window.addEventListener('scroll', rememberScroll, { passive: true });

        return () => window.removeEventListener('scroll', rememberScroll);
    }, [isDirty]);

    useEffect(() => {
        dirtyRef.current = isDirty;

        if (!isDirty) {
            return;
        }

        /**
         * Empty this entry and push a copy above it, so the next Back lands on
         * an entry Inertia restores without remounting the page.
         */
        const arm = () => {
            if (armedRef.current) {
                return;
            }

            const state = window.history.state;

            if (
                state === null ||
                typeof state !== 'object' ||
                !('page' in state)
            ) {
                // No Inertia entry to copy (the first load of a session): there
                // is nothing to trap, so Back stays unguarded.
                return;
            }

            homeRef.current = state;
            window.history.replaceState(null, '', window.location.href);
            window.history.pushState(
                { ...state, [HISTORY_MARKER]: true },
                '',
                window.location.href,
            );
            armedRef.current = true;
        };

        const restoreScroll = () => {
            const { x, y } = scrollRef.current;
            requestAnimationFrame(() => window.scrollTo(x, y));
        };

        /**
         * Pop our own entry back off, landing on the emptied one below it.
         * Inertia repairs that entry's URL and state without swapping the page,
         * so the draft survives. Resolves once the pop has happened.
         */
        const rewind = (): Promise<void> =>
            new Promise((resolve) => {
                if (!armedRef.current) {
                    resolve();

                    return;
                }

                let settled = false;
                const finish = () => {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    window.removeEventListener('popstate', finish);
                    rewindingRef.current = false;
                    restoreScroll();
                    resolve();
                };

                armedRef.current = false;
                homeRef.current = null;
                rewindingRef.current = true;
                window.addEventListener('popstate', finish);
                window.history.back();
                // Nothing to go back to: do not hang the caller.
                window.setTimeout(finish, 250);
            });

        const onPopState = () => {
            if (rewindingRef.current) {
                return;
            }

            if (bypassRef.current) {
                bypassRef.current = false;

                return;
            }

            if (!dirtyRef.current || !armedRef.current) {
                return;
            }

            // The trap fired: Back landed on the emptied entry, the page is
            // still mounted and the draft is intact.
            armedRef.current = false;
            restoreScroll();

            if (actionRef.current !== null) {
                // A visit is already waiting on the confirm; do not replace it.
                return;
            }

            actionRef.current = () => {
                bypassRef.current = true;
                window.history.back();
            };
            setPending(true);
        };

        const offBefore = router.on('before', (event) => {
            // Hover prefetches are not navigations and must not prompt.
            if (event.detail.visit.prefetch) {
                return;
            }

            if (bypassRef.current || !dirtyRef.current) {
                return;
            }

            if (allowRef.current?.(event.detail.visit)) {
                return;
            }

            event.preventDefault();
            actionRef.current = () => {
                bypassRef.current = true;
                dirtyRef.current = false;
                router.visit(
                    event.detail.visit.url,
                    replayOptions(event.detail.visit),
                );
                bypassRef.current = false;
            };
            setPending(true);
        });

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('popstate', onPopState);
        rewindRef.current = rewind;
        arm();

        return () => {
            dirtyRef.current = false;
            rewindRef.current = null;
            offBefore();
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('popstate', onPopState);
            // Only pop our own entry: a confirmed visit has already pushed the
            // next page above it, and popping then would return the user here.
            const current = window.history.state as Record<
                string,
                unknown
            > | null;

            if (current !== null && current?.[HISTORY_MARKER] === true) {
                void rewind();
            } else {
                armedRef.current = false;
                homeRef.current = null;
            }
        };
    }, [isDirty]);

    const request = useCallback((action: () => void) => {
        if (!dirtyRef.current) {
            action();

            return;
        }

        actionRef.current = action;
        setPending(true);
    }, []);

    const rearm = useCallback(() => {
        const state = homeRef.current;

        if (state === null) {
            return;
        }

        window.history.replaceState(null, '', window.location.href);
        window.history.pushState(
            { ...(state as Record<string, unknown>), [HISTORY_MARKER]: true },
            '',
            window.location.href,
        );
        armedRef.current = true;
    }, []);

    const confirm = useCallback(() => {
        const action = actionRef.current;
        actionRef.current = null;
        setPending(false);

        void (async () => {
            // Pop our own entry first, so the visit (or the local action) lands
            // on the page's real entry and leaves no extra history behind.
            if (armedRef.current) {
                await rewindRef.current?.();
            }

            action?.();
        })();
    }, []);

    const cancel = useCallback(() => {
        actionRef.current = null;
        setPending(false);
        // A cancelled Back press left the emptied entry as the current one.
        if (dirtyRef.current) {
            rearm();
        }
    }, [rearm]);

    return { pending, confirm, cancel, request };
}

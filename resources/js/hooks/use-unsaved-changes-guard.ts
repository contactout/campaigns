import type { PendingVisit, VisitOptions } from '@inertiajs/core';
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

/**
 * A replay must carry the visit's own callbacks: a `useForm` submit builds its
 * `onSuccess`/`onError`/`onFinish` inside the form, and dropping them means a
 * failed save renders no error and a successful one never refreshes the dirty
 * baseline. Only the four runtime flags and the resolved URL are internal.
 */
const replayOptions = (visit: PendingVisit): VisitOptions => {
    const {
        id: _id,
        url: _url,
        cancelled: _cancelled,
        completed: _completed,
        interrupted: _interrupted,
        ...options
    } = visit;

    return options;
};

/**
 * Keeps a page's unsaved draft from being thrown away by a navigation.
 *
 * Three paths lose a draft and each needs its own mechanism:
 *
 * - **In-app visits** (`<Link>`, `router.visit`, `useForm`/`<Form>` submits,
 *   head actions that redirect) fire a cancelable `inertia:before` event, so
 *   the visit is cancelled here and replayed, with its callbacks, once the user
 *   confirms.
 * - **Browser Back** never fires `before`. Inertia's own `popstate` handler
 *   restores the previous entry with `preserveState: false`, which remounts the
 *   page and takes the draft with it, and it is not cancelable: a capture-phase
 *   listener with `stopImmediatePropagation()` was tried and, in the Chromium
 *   build used for the proofs, the page still swapped. What can be used is the
 *   one branch it does not swap on: an entry whose history state is `null` only
 *   has its URL restored. So while the draft is dirty the current entry is
 *   emptied and a copy is pushed above it. Back lands on the emptied entry, the
 *   page stays mounted, and the dialog asks. The trap is re-armed as soon as it
 *   fires, so a second Back cannot leave, and it is taken off before any visit
 *   the page allows through.
 * - **A closed tab or a hard reload** cannot be intercepted by a dialog, so
 *   the browser's own `beforeunload` confirmation is the guard there.
 *
 * The hook writes the page's state back into the emptied entry itself rather
 * than relying on Inertia's repair: `History.replaceState` returns early when
 * the state it would write equals its current one, so after the first repair in
 * a page's life a later one can be skipped and leave a `null` entry behind.
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
    const armedHrefRef = useRef<string | null>(null);
    const rewindingRef = useRef(false);
    const rewindRef = useRef<(() => Promise<void>) | null>(null);
    // The page's own history state, written back into the emptied entry.
    const pageStateRef = useRef<Record<string, unknown> | null>(null);
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

    /** Write the page's state back, whatever Inertia's own repair decided. */
    const repair = useCallback(() => {
        const state = pageStateRef.current;

        if (state !== null) {
            window.history.replaceState(state, '', window.location.href);
        }
    }, []);

    /**
     * Empty the current entry and push a copy above it, so the next Back lands
     * on an entry Inertia restores without remounting the page.
     */
    const rearm = useCallback(() => {
        if (armedRef.current) {
            return;
        }

        const state = pageStateRef.current;

        if (state === null) {
            return;
        }

        const href = window.location.href;
        window.history.replaceState(null, '', href);
        window.history.pushState({ ...state }, '', href);
        armedRef.current = true;
        armedHrefRef.current = href;
    }, []);

    useEffect(() => {
        dirtyRef.current = isDirty;

        if (!isDirty) {
            return;
        }

        const rememberPageState = () => {
            const state = window.history.state as Record<
                string,
                unknown
            > | null;

            // No Inertia entry to copy (the first load of a session): there is
            // nothing to trap, so Back stays unguarded.
            if (
                state === null ||
                typeof state !== 'object' ||
                !('page' in state)
            ) {
                return false;
            }

            pageStateRef.current = state;

            return true;
        };

        const restoreScroll = () => {
            const { x, y } = scrollRef.current;
            requestAnimationFrame(() => window.scrollTo(x, y));
        };

        /**
         * Pop our own copy off, landing on the emptied entry below it. Inertia
         * restores that entry's URL without swapping the page, and the state is
         * written back here so the entry is valid for whoever comes next.
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
                    armedRef.current = false;
                    armedHrefRef.current = null;
                    repair();
                    restoreScroll();
                    resolve();
                };

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

            // The trap fired: Back landed on the emptied entry, so the page is
            // still mounted and the draft is intact.
            armedRef.current = false;
            armedHrefRef.current = null;
            repair();
            restoreScroll();

            if (actionRef.current !== null) {
                // A Back with the confirm already open is the platform's way of
                // dismissing it, and a second Back must not leave either.
                actionRef.current = null;
                setPending(false);
                rearm();

                return;
            }

            actionRef.current = () => {
                bypassRef.current = true;
                window.history.back();
            };
            setPending(true);
            // Trapped again immediately: a second Back asks rather than leaves.
            rearm();
        };

        const offBefore = router.on('before', (event) => {
            const visit = event.detail.visit;

            // Hover prefetches are not navigations and must not prompt.
            if (visit.prefetch) {
                return;
            }

            if (bypassRef.current || !dirtyRef.current) {
                return;
            }

            if (allowRef.current?.(visit)) {
                // A same-page reload (the editor's own save, the placeholder
                // rail, a step endpoint) keeps the draft. Take the trap off
                // first, so the save does not leave the emptied entry behind,
                // then replay the visit with its own callbacks intact.
                if (armedRef.current) {
                    event.preventDefault();

                    void rewind().then(() => {
                        bypassRef.current = true;
                        router.visit(visit.url, replayOptions(visit));
                        bypassRef.current = false;
                    });
                }

                return;
            }

            event.preventDefault();
            actionRef.current = () => {
                bypassRef.current = true;
                router.visit(visit.url, replayOptions(visit));
                bypassRef.current = false;
            };
            setPending(true);
        });

        const offFinish = router.on('finish', () => {
            // A save that failed (a 422 keeps the draft) or a reload that left
            // the page dirty must re-arm the trap it was rewound for.
            if (dirtyRef.current) {
                rearm();
            }
        });

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        rewindRef.current = rewind;
        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('popstate', onPopState);

        if (rememberPageState()) {
            rearm();
        }

        return () => {
            dirtyRef.current = false;
            rewindRef.current = null;
            offBefore();
            offFinish();
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('popstate', onPopState);

            // Only rewind while we are still on the entry we armed: a confirmed
            // visit has already pushed the next page above it, and popping then
            // would return the user here.
            if (
                armedRef.current &&
                pageStateRef.current !== null &&
                window.location.href === armedHrefRef.current
            ) {
                void rewind();
            } else {
                armedRef.current = false;
                armedHrefRef.current = null;
            }
        };
    }, [isDirty, rearm, repair]);

    const request = useCallback((action: () => void) => {
        if (!dirtyRef.current) {
            action();

            return;
        }

        actionRef.current = action;
        setPending(true);
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

        if (dirtyRef.current) {
            rearm();
        }
    }, [rearm]);

    return { pending, confirm, cancel, request };
}

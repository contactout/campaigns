import { useSyncExternalStore } from 'react';

// The product shell drops its sidebar at lg (64rem), so "mobile" means
// "below the sidebar breakpoint": the 390 bottom tab bar and the page-head
// sheet trigger replace the rail there. Settings and auth inherit the same
// line, which keeps their navigation reachable at every width.
const MOBILE_BREAKPOINT = 1024;

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}

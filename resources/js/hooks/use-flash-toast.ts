import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

let suppressed = false;

/**
 * Silence flash toasts while a client-side bulk action owns the feedback.
 * The bulk action shows one summary toast when it finishes.
 */
export function setFlashToastSuppressed(value: boolean): void {
    suppressed = value;
}

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data || suppressed) {
                return;
            }

            toast[data.type](data.message, { id: data.message });
        });
    }, []);
}

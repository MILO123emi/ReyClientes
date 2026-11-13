import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useConfirmPassword } from '@/composables/useConfirmPassword';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('navigate', () => {
        useConfirmPassword().complete(null);
    });

    router.on('httpException', (event) => {
        const response = event.detail.response;

        if (response.status !== 429) {
            return;
        }

        event.preventDefault();

        const retryAfter = Number(response.headers['retry-after']);
        const minutes = Number.isFinite(retryAfter)
            ? Math.max(1, Math.ceil(retryAfter / 60))
            : 5;
        const fallback = `Demasiados intentos, espera ${minutes} minuto(s).`;
        const message =
            typeof response.data === 'string'
                ? response.data.match(
                      /Demasiados intentos, espera \d+ minuto\(s\)\./,
                  )?.[0]
                : response.data.message;

        toast.error(typeof message === 'string' ? message : fallback);
    });

    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}

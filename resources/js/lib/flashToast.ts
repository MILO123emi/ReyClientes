import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useConfirmPassword } from '@/composables/useConfirmPassword';
import type { FlashToast } from '@/types/ui';

/** Spanish message for an error response, or null to keep Inertia's default error window. */
function httpErrorMessage(status: number): string | null {
    switch (status) {
        case 403:
            return 'No tienes permiso para hacer eso.';
        case 404:
            return 'Ese registro ya no existe. Actualiza la página.';
        case 413:
            return 'La foto pesa demasiado. Elige una de menos de 8 MB.';
        case 419:
            return 'Tu sesión venció. Vuelve a iniciar sesión.';
        case 503:
            return 'El sistema está en mantenimiento. Intenta en unos minutos.';
        default:
            // While developing, the full error page is more useful than a toast.
            return status >= 500 && !import.meta.env.DEV
                ? 'Ocurrió un error. Intenta de nuevo.'
                : null;
    }
}

export function initializeFlashToast(): void {
    router.on('navigate', () => {
        useConfirmPassword().complete(null);
    });

    router.on('httpException', (event) => {
        const response = event.detail.response;

        if (response.status !== 429) {
            const message = httpErrorMessage(response.status);

            if (message !== null) {
                event.preventDefault();
                toast.error(message);

                if (response.status === 419) {
                    setTimeout(() => window.location.reload(), 2000);
                }
            }

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

    router.on('networkError', (event) => {
        event.preventDefault();
        toast.error('No hay conexión. Revisa el internet e intenta de nuevo.');
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

import type { Errors } from '@inertiajs/core';
import {
    useConfirmPassword,
    type ConfirmPasswordOptions,
} from '@/composables/useConfirmPassword';

export type PasswordVisitCallbacks = {
    onError: (errors: Errors) => void;
    onFinish: () => void;
};

export async function submitWithPassword(
    message: string,
    send: (password: string, callbacks: PasswordVisitCallbacks) => void,
    options: ConfirmPasswordOptions = {},
): Promise<void> {
    const { confirm } = useConfirmPassword();
    let error = options.error;

    while (true) {
        const password = await confirm(message, { ...options, error });

        if (password === null) {
            return;
        }

        let confirmationError: string | undefined;

        await new Promise<void>((resolve, reject) => {
            try {
                send(password, {
                    onError: (errors) => {
                        confirmationError = errors.current_password;
                    },
                    onFinish: resolve,
                });
            } catch (exception) {
                reject(exception);
            }
        });

        if (!confirmationError) {
            return;
        }

        error = confirmationError;
    }
}

import { reactive, readonly } from 'vue';

export type ConfirmPasswordOptions = {
    description?: string;
    destructive?: boolean;
    error?: string;
    confirmLabel?: string;
};

const state = reactive({
    open: false,
    requestId: 0,
    message: '',
    description: '',
    destructive: false,
    error: '',
    confirmLabel: '',
});

let pending: ((password: string | null) => void) | null = null;

function complete(password: string | null): void {
    const resolve = pending;
    pending = null;
    state.open = false;
    state.message = '';
    state.description = '';
    state.destructive = false;
    state.error = '';
    state.confirmLabel = '';
    resolve?.(password);
}

function confirm(
    message: string,
    options: ConfirmPasswordOptions = {},
): Promise<string | null> {
    complete(null);
    state.requestId += 1;
    state.message = message;
    state.description = options.description ?? '';
    state.destructive = options.destructive ?? false;
    state.error = options.error ?? '';
    state.confirmLabel = options.confirmLabel ?? '';
    state.open = true;

    return new Promise((resolve) => {
        pending = resolve;
    });
}

export function useConfirmPassword() {
    return { state: readonly(state), confirm, complete };
}

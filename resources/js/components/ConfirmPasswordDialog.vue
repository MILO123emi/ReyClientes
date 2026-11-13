<script setup lang="ts">
import { ShieldCheck, TriangleAlert } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useConfirmPassword } from '@/composables/useConfirmPassword';

const { state, complete } = useConfirmPassword();
const password = ref('');

watch(
    [() => state.requestId, () => state.open],
    () => {
        password.value = '';
    },
    { flush: 'sync' },
);

function finish(value: string | null): void {
    password.value = '';
    complete(value);
}

onBeforeUnmount(() => finish(null));
</script>

<template>
    <Dialog :open="state.open" @update:open="!$event && finish(null)">
        <DialogContent
            :key="state.requestId"
            class="form-dialog max-h-dvh overflow-y-auto max-sm:h-dvh max-sm:max-w-none max-sm:rounded-none sm:max-h-[90dvh] sm:rounded-2xl"
        >
            <DialogHeader>
                <div
                    class="mb-2 flex size-14 items-center justify-center rounded-full"
                    :class="
                        state.destructive
                            ? 'bg-destructive-soft text-destructive-soft-foreground'
                            : 'bg-brand-blue-soft text-blue-soft-foreground'
                    "
                >
                    <TriangleAlert
                        v-if="state.destructive"
                        class="size-7"
                    /><ShieldCheck v-else class="size-7" />
                </div>
                <DialogTitle>{{ state.message }}</DialogTitle>
                <DialogDescription
                    :class="
                        state.destructive
                            ? 'text-destructive-soft-foreground'
                            : ''
                    "
                >
                    {{
                        state.description ||
                        'Escribe tu contraseña para confirmar esta acción.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form
                id="confirm-action-form"
                class="flex flex-col gap-5"
                @submit.prevent="finish(password)"
            >
                <div class="grid gap-2">
                    <Label for="action-password">Contraseña</Label>
                    <PasswordInput
                        id="action-password"
                        v-model="password"
                        autocomplete="current-password"
                        autofocus
                        required
                        :aria-invalid="Boolean(state.error)"
                        :aria-describedby="
                            state.error ? 'action-password-error' : undefined
                        "
                    />
                    <InputError
                        id="action-password-error"
                        :message="state.error"
                    />
                </div>
            </form>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    @click="finish(null)"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    form="confirm-action-form"
                    class="min-h-11"
                    :variant="state.destructive ? 'destructive' : 'default'"
                    :disabled="password.length === 0"
                >
                    {{
                        state.confirmLabel ||
                        (state.destructive ? 'Eliminar' : 'Confirmar')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

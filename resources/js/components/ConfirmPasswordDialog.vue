<script setup lang="ts">
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
            class="max-h-dvh overflow-y-auto max-sm:h-dvh max-sm:max-w-none max-sm:rounded-none sm:max-h-[90dvh]"
        >
            <DialogHeader>
                <DialogTitle>{{ state.message }}</DialogTitle>
                <DialogDescription
                    :class="state.destructive ? 'text-destructive' : ''"
                >
                    {{
                        state.description ||
                        'Escribe tu contraseña para confirmar esta acción.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="finish(password)">
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
            </form>
        </DialogContent>
    </Dialog>
</template>

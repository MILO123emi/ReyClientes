<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { password as setPassword } from '@/routes/users';
import type { ManagedUser } from '@/types/management';

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    user: ManagedUser | null;
    passwordRules: string;
}>();
const confirming = ref(false);
const form = useForm({ password: '', password_confirmation: '' });
watch(open, () => {
    form.reset();
    form.clearErrors();
});

async function submit(): Promise<void> {
    if (!props.user || form.processing || confirming.value) return;
    const userId = props.user.id;
    confirming.value = true;
    try {
        await submitWithPassword(
            'Confirma tu contraseña para asignar la contraseña temporal',
            (password, callbacks) => {
                form.transform((data) => ({
                    ...data,
                    current_password: password,
                })).put(setPassword.url(userId), {
                    ...callbacks,
                    preserveScroll: true,
                    onSuccess: () => {
                        open.value = false;
                        form.reset();
                    },
                    onFinish: () => {
                        form.transform((data) => data);
                        callbacks.onFinish();
                    },
                });
            },
        );
    } finally {
        confirming.value = false;
        form.reset();
    }
}
function setOpen(value: boolean): void {
    if (!form.processing && !confirming.value) open.value = value;
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen">
        <DialogContent
            class="form-dialog max-h-dvh overflow-y-auto max-sm:h-dvh max-sm:max-w-none max-sm:rounded-none sm:max-h-[90dvh] sm:rounded-2xl"
        >
            <DialogHeader
                ><DialogTitle>Asignar contraseña</DialogTitle
                ><DialogDescription
                    >Se cerrarán las sesiones de {{ user?.name }} y deberá
                    cambiar la contraseña temporal al iniciar
                    sesión.</DialogDescription
                ></DialogHeader
            >
            <form
                id="set-user-password-form"
                class="flex flex-col gap-5"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="assigned-password">Contraseña temporal</Label
                    ><PasswordInput
                        id="assigned-password"
                        v-model="form.password"
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="assigned-password-confirmation"
                        >Confirmar contraseña temporal</Label
                    ><PasswordInput
                        id="assigned-password-confirmation"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        :disabled="form.processing"
                    /><InputError
                        :message="form.errors.password_confirmation"
                    />
                </div>
            </form>
            <DialogFooter
                ><Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="form.processing || confirming"
                    @click="setOpen(false)"
                    >Cancelar</Button
                ><Button
                    type="submit"
                    form="set-user-password-form"
                    class="min-h-11"
                    :disabled="form.processing || confirming"
                    ><Spinner v-if="form.processing" />{{
                        form.processing ? 'Guardando…' : 'Guardar'
                    }}</Button
                ></DialogFooter
            >
        </DialogContent>
    </Dialog>
</template>

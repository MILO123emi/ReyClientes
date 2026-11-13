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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { store } from '@/routes/users';

const open = defineModel<boolean>('open', { required: true });
defineProps<{ passwordRules: string }>();
const confirming = ref(false);
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
watch(open, () => {
    form.reset();
    form.clearErrors();
});

async function submit(): Promise<void> {
    if (confirming.value || form.processing) return;
    confirming.value = true;
    try {
        await submitWithPassword(
            'Confirma tu contraseña para crear el usuario',
            (password, callbacks) => {
                form.transform((data) => ({
                    ...data,
                    current_password: password,
                })).post(store.url(), {
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
        form.reset('password', 'password_confirmation');
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
                ><DialogTitle>Nuevo empleado</DialogTitle
                ><DialogDescription
                    >El usuario deberá cambiar la contraseña temporal al iniciar
                    sesión.</DialogDescription
                ></DialogHeader
            >
            <form
                id="user-form"
                class="flex flex-col gap-5"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="new-user-name">Nombre</Label
                    ><Input
                        id="new-user-name"
                        v-model="form.name"
                        maxlength="120"
                        autocomplete="name"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-user-email">Correo electrónico</Label
                    ><Input
                        id="new-user-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-user-password">Contraseña temporal</Label
                    ><PasswordInput
                        id="new-user-password"
                        v-model="form.password"
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-user-password-confirmation"
                        >Confirmar contraseña temporal</Label
                    ><PasswordInput
                        id="new-user-password-confirmation"
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
                    form="user-form"
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

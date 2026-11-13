<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/ForcedPasswordChangeController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { index } from '@/routes/clients';

type Props = {
    passwordRules: string;
    mustChange: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        component: AuthLayout,
        props: {
            title: 'Cambiar contraseña',
            description:
                'Usa una contraseña de al menos 8 caracteres para proteger tu cuenta',
        },
    },
});
</script>

<template>
    <div class="w-full space-y-6">
        <Head title="Cambiar contraseña" />

        <Alert v-if="mustChange" role="status">
            <AlertDescription
                >Debes cambiar la contraseña temporal antes de
                continuar</AlertDescription
            >
        </Alert>

        <div class="space-y-6">
            <Form
                v-bind="update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password">Contraseña actual</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        placeholder="Contraseña actual"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nueva contraseña</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        placeholder="Nueva contraseña"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation"
                        >Confirmar nueva contraseña</Label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        placeholder="Confirmar nueva contraseña"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex flex-col gap-3">
                    <Button
                        class="min-h-12 w-full"
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        <Spinner v-if="processing" />
                        {{ processing ? 'Guardando…' : 'Guardar' }}
                    </Button>
                </div>
            </Form>
            <div
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <Link
                    v-if="!mustChange"
                    :href="index()"
                    class="inline-flex min-h-11 items-center text-link underline"
                    >Volver a clientes</Link
                ><Link
                    :href="logout()"
                    as="button"
                    class="inline-flex min-h-11 items-center text-link underline"
                    >Cerrar sesión</Link
                >
            </div>
        </div>
    </div>
</template>

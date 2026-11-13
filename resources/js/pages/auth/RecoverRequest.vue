<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { send } from '@/routes/recovery';

defineOptions({
    layout: {
        title: 'Recuperar contraseña',
        description:
            'El código por correo es solo para la administradora. Los empleados deben pedirle a ella una contraseña nueva.',
    },
});
defineProps<{ status?: string | null }>();
const form = useForm({ email: '' });
function submit(): void {
    form.post(send.url());
}
</script>

<template>
    <div class="grid gap-6">
        <Head title="Recuperar contraseña" />
        <p v-if="status" role="status" class="text-sm text-link">
            {{ status }}
        </p>
        <form class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="recovery-email">Correo electrónico</Label
                ><Input
                    id="recovery-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    v-focus
                    class="min-h-11"
                    :disabled="form.processing"
                /><InputError :message="form.errors.email" />
            </div>
            <Button
                type="submit"
                class="min-h-11 w-full"
                :disabled="form.processing"
                ><Spinner v-if="form.processing" />{{
                    form.processing ? 'Enviando…' : 'Enviar código'
                }}</Button
            >
        </form>
        <Link
            :href="login()"
            class="inline-flex min-h-11 items-center justify-center text-sm text-link underline underline-offset-4"
            >Volver al inicio de sesión</Link
        >
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import IdPhotoInput from '@/components/IdPhotoInput.vue';
import InputError from '@/components/InputError.vue';
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
import { formatDni, formatPhone } from '@/lib/format';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { store, update } from '@/routes/clients';
import type { Client } from '@/types/cards';

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{ client?: Client | null }>();
const confirming = ref(false);
const photoError = ref('');
const form = useForm({
    _method: 'post',
    full_name: '',
    dni: '',
    phone: '',
    id_photo: null as File | null,
});

watch(open, (value) => {
    if (!value) return;
    form.defaults({
        _method: props.client ? 'put' : 'post',
        full_name: props.client?.full_name ?? '',
        dni: props.client?.dni ?? '',
        phone: props.client?.phone ?? '',
        id_photo: null,
    });
    form.reset();
    form.clearErrors();
    photoError.value = '';
});

async function submit(): Promise<void> {
    if (confirming.value || form.processing || photoError.value) return;
    confirming.value = true;
    try {
        await submitWithPassword(
            'Confirma tu contraseña para guardar el cliente',
            (password, callbacks) => {
                form.transform((data) => ({
                    ...data,
                    current_password: password,
                })).post(
                    props.client ? update.url(props.client.id) : store.url(),
                    {
                        ...callbacks,
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            open.value = false;
                            form.reset();
                        },
                        onFinish: () => {
                            form.transform((data) => data);
                            callbacks.onFinish();
                        },
                    },
                );
            },
        );
    } finally {
        confirming.value = false;
    }
}

function setOpen(value: boolean): void {
    if (!form.processing && !confirming.value) open.value = value;
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen">
        <DialogContent
            class="max-h-dvh overflow-y-auto max-sm:h-dvh max-sm:max-w-none max-sm:rounded-none sm:max-h-[90dvh]"
        >
            <DialogHeader>
                <DialogTitle>{{
                    client ? 'Editar cliente' : 'Nuevo cliente'
                }}</DialogTitle>
                <DialogDescription
                    >Datos del titular de la tarjeta. Todos los campos son
                    obligatorios al crear.</DialogDescription
                >
            </DialogHeader>
            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="client-name">Nombre completo</Label>
                    <Input
                        id="client-name"
                        v-model="form.full_name"
                        autocomplete="name"
                        maxlength="120"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors.full_name)"
                    />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="client-dni">Identidad</Label>
                    <Input
                        id="client-dni"
                        :model-value="form.dni"
                        inputmode="numeric"
                        placeholder="0801-1990-12345"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors.dni)"
                        @update:model-value="
                            form.dni = formatDni(String($event))
                        "
                    />
                    <InputError :message="form.errors.dni" />
                </div>
                <div class="grid gap-2">
                    <Label for="client-phone">Teléfono</Label>
                    <Input
                        id="client-phone"
                        :model-value="form.phone"
                        type="tel"
                        autocomplete="tel-national"
                        placeholder="9999-9999"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors.phone)"
                        @update:model-value="
                            form.phone = formatPhone(String($event))
                        "
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label
                        >Foto de la cédula{{
                            client ? ' (opcional si no cambia)' : ''
                        }}</Label
                    >
                    <IdPhotoInput
                        v-model="form.id_photo"
                        :current-photo="client?.photo.thumb"
                        :disabled="form.processing"
                        @error="photoError = $event"
                    />
                    <InputError :message="form.errors.id_photo" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="form.processing || confirming"
                        @click="setOpen(false)"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="min-h-11"
                        :disabled="
                            form.processing || confirming || Boolean(photoError)
                        "
                        ><LoaderCircle
                            v-if="form.processing"
                            class="animate-spin"
                        />{{
                            form.processing ? 'Guardando…' : 'Guardar cliente'
                        }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

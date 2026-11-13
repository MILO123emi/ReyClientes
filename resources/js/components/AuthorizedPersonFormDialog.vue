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
import { store, update } from '@/routes/people';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AuthorizedPerson,
    Client,
    RelationshipOption,
} from '@/types/cards';

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    client: Client | null;
    person?: AuthorizedPerson | null;
    relationships: RelationshipOption[];
}>();
const confirming = ref(false);
const photoError = ref('');
const form = useForm({
    _method: 'post',
    full_name: '',
    dni: '',
    phone: '',
    id_photo: null as File | null,
    relationship: '',
    relationship_other: '',
});

watch(open, (value) => {
    if (!value) return;
    form.defaults({
        _method: props.person ? 'put' : 'post',
        full_name: props.person?.full_name ?? '',
        dni: props.person?.dni ?? '',
        phone: props.person?.phone ?? '',
        id_photo: null,
        relationship: props.person?.relationship ?? '',
        relationship_other: props.person?.relationship_other ?? '',
    });
    form.reset();
    form.clearErrors();
    photoError.value = '';
});

watch(
    () => form.relationship,
    (value) => {
        if (value !== 'otro') form.relationship_other = '';
    },
);

async function submit(): Promise<void> {
    if (
        !props.client ||
        confirming.value ||
        form.processing ||
        photoError.value
    )
        return;
    confirming.value = true;
    try {
        await submitWithPassword(
            'Confirma tu contraseña para guardar la persona autorizada',
            (password, callbacks) => {
                form.transform((data) => ({
                    ...data,
                    current_password: password,
                })).post(
                    props.person
                        ? update.url(props.person.id)
                        : store.url(props.client!.id),
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
                    person
                        ? 'Editar persona autorizada'
                        : 'Agregar persona autorizada'
                }}</DialogTitle>
                <DialogDescription
                    >Cliente: {{ client?.full_name }}. Todos los campos son
                    obligatorios al crear.</DialogDescription
                >
            </DialogHeader>
            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="person-name">Nombre completo</Label>
                    <Input
                        id="person-name"
                        v-model="form.full_name"
                        autocomplete="name"
                        maxlength="120"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors.full_name)"
                    />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="person-dni">Identidad</Label>
                    <Input
                        id="person-dni"
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
                    <Label for="person-phone">Teléfono</Label>
                    <Input
                        id="person-phone"
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
                    <Label for="person-relationship">Parentesco</Label>
                    <Select
                        v-model="form.relationship"
                        :disabled="form.processing"
                    >
                        <SelectTrigger
                            id="person-relationship"
                            class="min-h-11 w-full"
                            :aria-invalid="Boolean(form.errors.relationship)"
                            ><SelectValue
                                placeholder="Selecciona un parentesco"
                        /></SelectTrigger>
                        <SelectContent
                            ><SelectItem
                                v-for="relationship in relationships"
                                :key="relationship.value"
                                :value="relationship.value"
                                class="min-h-11"
                                >{{ relationship.label }}</SelectItem
                            ></SelectContent
                        >
                    </Select>
                    <InputError :message="form.errors.relationship" />
                </div>
                <div v-if="form.relationship === 'otro'" class="grid gap-2">
                    <Label for="person-relationship-other">Especificar</Label>
                    <Input
                        id="person-relationship-other"
                        v-model="form.relationship_other"
                        maxlength="50"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors.relationship_other)"
                    />
                    <InputError :message="form.errors.relationship_other" />
                </div>
                <div class="grid gap-2">
                    <Label
                        >Foto de la cédula{{
                            person ? ' (opcional si no cambia)' : ''
                        }}</Label
                    >
                    <IdPhotoInput
                        v-model="form.id_photo"
                        :current-photo="person?.photo.thumb"
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
                            form.processing
                                ? 'Guardando…'
                                : 'Guardar persona autorizada'
                        }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

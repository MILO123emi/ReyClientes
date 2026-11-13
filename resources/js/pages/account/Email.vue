<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { confirm, destroy, store } from '@/routes/account/email';

const props = defineProps<{
    email: string;
    pending: { email: string; expiresAt: string } | null;
}>();
const requestForm = useForm({ email: '' });
const codeForm = useForm({ code: '' });
const sending = ref(false);
const cancelling = ref(false);
const now = ref(Date.now());
const remaining = computed(() =>
    props.pending
        ? Math.max(
              0,
              Math.ceil(
                  (Date.parse(props.pending.expiresAt) - now.value) / 1000,
              ),
          )
        : 0,
);
const countdown = computed(
    () =>
        `${Math.floor(remaining.value / 60)
            .toString()
            .padStart(2, '0')}:${(remaining.value % 60)
            .toString()
            .padStart(2, '0')}`,
);
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    clock = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onBeforeUnmount(() => {
    clearInterval(clock);
    codeForm.reset();
});

async function requestChange(): Promise<void> {
    if (sending.value) return;
    sending.value = true;
    requestForm.clearErrors();
    try {
        await submitWithPassword(
            'Confirma tu contraseña para cambiar el correo',
            (password, callbacks) => {
                requestForm
                    .transform((data) => ({
                        ...data,
                        current_password: password,
                    }))
                    .post(store.url(), {
                        ...callbacks,
                        preserveScroll: true,
                        onSuccess: () => requestForm.reset(),
                    });
            },
        );
    } finally {
        sending.value = false;
    }
}

function confirmCode(): void {
    codeForm.post(confirm.url(), {
        preserveScroll: true,
        onFinish: () => codeForm.reset('code'),
    });
}

function cancel(): void {
    router.delete(destroy.url(), {
        preserveScroll: true,
        onStart: () => (cancelling.value = true),
        onFinish: () => (cancelling.value = false),
    });
}
</script>

<template>
    <div class="mx-auto grid w-full max-w-xl gap-5">
        <Head title="Correo de la cuenta" />
        <PageHeader
            title="Correo de la cuenta"
            subtitle="A este correo llega el código para recuperar tu contraseña"
        />
        <section class="rounded-2xl border bg-card p-5 shadow-card">
            <p class="text-sm text-muted-foreground">Correo actual</p>
            <p class="mt-1 text-[17px] font-semibold break-all">{{ email }}</p>
        </section>

        <section
            v-if="pending"
            class="grid gap-4 rounded-2xl border bg-card p-5 shadow-card"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue-soft text-blue-soft-foreground"
                >
                    <MailCheck class="size-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-[17px] font-semibold">
                        Revisa tu nuevo correo
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Enviamos un código de 6 dígitos a
                        <strong class="break-all text-foreground">{{
                            pending.email
                        }}</strong
                        >. Tu correo no cambia hasta que lo escribas aquí.
                    </p>
                </div>
            </div>
            <form class="grid gap-3" @submit.prevent="confirmCode">
                <Label for="email-change-code">Código de 6 dígitos</Label>
                <Input
                    id="email-change-code"
                    v-model="codeForm.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    class="h-14 rounded-xl text-center text-2xl font-semibold tracking-[0.4em] tabular-nums"
                    :disabled="codeForm.processing"
                    :aria-invalid="Boolean(codeForm.errors.code)"
                />
                <InputError :message="codeForm.errors.code" />
                <p
                    role="timer"
                    aria-live="off"
                    class="text-sm"
                    :class="
                        remaining === 0
                            ? 'text-destructive-soft-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    {{
                        remaining > 0
                            ? `El código vence en ${countdown}.`
                            : 'El código venció. Cancela y pide uno nuevo.'
                    }}
                </p>
                <Button
                    type="submit"
                    class="min-h-12 w-full"
                    :disabled="
                        codeForm.processing ||
                        codeForm.code.length !== 6 ||
                        remaining === 0
                    "
                    ><Spinner v-if="codeForm.processing" />{{
                        codeForm.processing
                            ? 'Confirmando…'
                            : 'Confirmar nuevo correo'
                    }}</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full"
                    :disabled="codeForm.processing || cancelling"
                    @click="cancel"
                    ><Spinner v-if="cancelling" />Cancelar y escribir otro
                    correo</Button
                >
            </form>
        </section>

        <section v-else class="rounded-2xl border bg-card p-5 shadow-card">
            <h2 class="text-[17px] font-semibold">Cambiar correo</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Te enviaremos un código al correo nuevo para comprobar que está
                bien escrito. Mientras no lo confirmes, sigues usando el correo
                actual.
            </p>
            <form class="mt-4 grid gap-3" @submit.prevent="requestChange">
                <Label for="new-email">Correo nuevo</Label>
                <Input
                    id="new-email"
                    v-model="requestForm.email"
                    type="email"
                    autocomplete="email"
                    maxlength="255"
                    required
                    class="h-12 rounded-xl"
                    :disabled="sending"
                    :aria-invalid="Boolean(requestForm.errors.email)"
                />
                <InputError :message="requestForm.errors.email" />
                <Button
                    type="submit"
                    class="min-h-12 w-full"
                    :disabled="sending || !requestForm.email"
                    ><Spinner v-if="sending" />Enviar código</Button
                >
            </form>
        </section>
    </div>
</template>

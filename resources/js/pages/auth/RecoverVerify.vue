<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { send } from '@/routes/recovery';
import { store } from '@/routes/recovery/verify';

defineOptions({
    layout: {
        title: 'Ingresa el código de recuperación',
        description: 'Escribe el código de seis dígitos y tu nueva contraseña.',
    },
});
const props = defineProps<{
    status?: string | null;
    sentAt: string | null;
    passwordRules: string;
}>();
const digits = ref<string[]>(Array(6).fill(''));
const codeInputs: HTMLInputElement[] = [];
const form = useForm({ code: '', password: '', password_confirmation: '' });
const resend = useForm({});
const now = ref(Date.now());
const remaining = computed(() => {
    const sent = props.sentAt ? Date.parse(props.sentAt) : NaN;
    return Number.isFinite(sent)
        ? Math.max(0, Math.ceil((sent + 600_000 - now.value) / 1000))
        : 0;
});
const countdown = computed(
    () =>
        `${Math.floor(remaining.value / 60)
            .toString()
            .padStart(
                2,
                '0',
            )}:${(remaining.value % 60).toString().padStart(2, '0')}`,
);
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    clock = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onBeforeUnmount(() => {
    clearInterval(clock);
    form.reset();
    digits.value.fill('');
});

function fillCode(value: string, start = 0): void {
    const values = value.replace(/\D/g, '').slice(0, 6);
    if (!values) return;
    const offset = values.length === 6 ? 0 : start;
    for (let index = 0; index < values.length && offset + index < 6; index += 1)
        digits.value[offset + index] = values[index];
    codeInputs[Math.min(5, offset + values.length)]?.focus();
}
function inputDigit(event: Event, index: number): void {
    const target = event.target as HTMLInputElement;
    const value = target.value.replace(/\D/g, '');
    if (value.length > 1) fillCode(value, index);
    else {
        digits.value[index] = value;
        if (value) codeInputs[index + 1]?.focus();
    }
    target.value = digits.value[index];
}
function keyDown(event: KeyboardEvent, index: number): void {
    if (event.key === 'Backspace' && !digits.value[index])
        codeInputs[index - 1]?.focus();
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        codeInputs[index - 1]?.focus();
    }
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        codeInputs[index + 1]?.focus();
    }
}
function pasteCode(event: ClipboardEvent): void {
    event.preventDefault();
    fillCode(event.clipboardData?.getData('text') ?? '');
}
function submit(): void {
    form.code = digits.value.join('');
    form.post(store.url(), {
        onFinish: () => form.reset('password', 'password_confirmation', 'code'),
    });
}
function resendCode(): void {
    resend.post(send.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            digits.value.fill('');
            now.value = Date.now();
            codeInputs[0]?.focus();
        },
    });
}
</script>

<template>
    <div class="grid gap-5">
        <Head title="Verificar código" />
        <p
            v-if="status"
            role="status"
            class="rounded-md bg-accent/40 p-3 text-sm"
        >
            {{ status }}
        </p>
        <p
            role="timer"
            aria-live="off"
            class="text-center text-sm"
            :class="
                remaining === 0
                    ? 'text-destructive-soft-foreground'
                    : 'text-muted-foreground'
            "
        >
            {{
                remaining > 0
                    ? `El código vence en ${countdown}.`
                    : 'El código venció. Pide uno nuevo.'
            }}
        </p>
        <form class="grid gap-5" @submit.prevent="submit">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Código de recuperación
                </legend>
                <div class="grid grid-cols-6 gap-2" @paste="pasteCode">
                    <input
                        v-for="(_, index) in digits"
                        :key="index"
                        :ref="
                            (element) => {
                                if (element)
                                    codeInputs[index] =
                                        element as HTMLInputElement;
                            }
                        "
                        :value="digits[index]"
                        type="text"
                        inputmode="numeric"
                        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                        maxlength="1"
                        :aria-label="`Dígito ${index + 1} de 6`"
                        class="h-12 min-w-0 rounded-md border bg-card text-center text-xl font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :disabled="form.processing || resend.processing"
                        :aria-invalid="Boolean(form.errors.code)"
                        @input="inputDigit($event, index)"
                        @keydown="keyDown($event, index)"
                    />
                </div>
                <InputError :message="form.errors.code" />
            </fieldset>
            <div class="grid gap-2">
                <Label for="recovered-password">Nueva contraseña</Label
                ><PasswordInput
                    id="recovered-password"
                    v-model="form.password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                    :disabled="form.processing || resend.processing"
                /><InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="recovered-password-confirmation"
                    >Confirmar nueva contraseña</Label
                ><PasswordInput
                    id="recovered-password-confirmation"
                    v-model="form.password_confirmation"
                    autocomplete="new-password"
                    :disabled="form.processing || resend.processing"
                /><InputError :message="form.errors.password_confirmation" />
            </div>
            <Button
                type="submit"
                class="min-h-11 w-full"
                :disabled="
                    form.processing ||
                    resend.processing ||
                    digits.join('').length !== 6 ||
                    remaining === 0
                "
                ><Spinner v-if="form.processing" />{{
                    form.processing ? 'Guardando…' : 'Cambiar contraseña'
                }}</Button
            >
        </form>
        <Button
            type="button"
            variant="outline"
            class="min-h-11 w-full"
            :disabled="form.processing || resend.processing"
            @click="resendCode"
            ><Spinner v-if="resend.processing" />Enviar otro código</Button
        >
        <Link
            :href="login()"
            class="inline-flex min-h-11 items-center justify-center text-sm text-link underline underline-offset-4"
            >Volver al inicio de sesión</Link
        >
    </div>
</template>

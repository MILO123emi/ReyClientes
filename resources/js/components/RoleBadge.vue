<script setup lang="ts">
import { CreditCard, UserCheck } from '@lucide/vue';

defineProps<{ role: 'owner' | 'authorized' }>();
</script>

<template>
    <span
        class="inline-flex min-h-7 w-fit max-w-full items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] leading-tight font-semibold"
        :class="
            role === 'owner'
                ? 'border-primary bg-primary text-white'
                : 'border-brand-yellow bg-accent text-accent-foreground'
        "
    >
        <component
            :is="role === 'owner' ? CreditCard : UserCheck"
            class="size-4 shrink-0"
            aria-hidden="true"
        />
        {{ role === 'owner' ? 'Dueño de la tarjeta' : 'Persona autorizada' }}
    </span>
</template>

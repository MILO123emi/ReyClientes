<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';

const model = defineModel<boolean>({ default: false });
defineProps<{ disabled?: boolean }>();
</script>

<template>
    <SwitchRoot v-model="model" :disabled="disabled" class="inline-flex h-11 w-16 shrink-0 items-center rounded-full bg-input p-2 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary">
        <SwitchThumb class="pointer-events-none block size-7 rounded-full bg-white shadow-sm transition-transform data-[state=checked]:translate-x-5" />
    </SwitchRoot>
</template>

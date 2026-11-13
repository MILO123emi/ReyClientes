<script setup lang="ts">
import { Plus } from '@lucide/vue';
defineProps<{ label: string }>();
defineEmits<{ click: [] }>();
</script>
<template>
    <button
        type="button"
        :aria-label="label"
        class="fixed right-4 bottom-[calc(80px+env(safe-area-inset-bottom))] z-20 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-colors hover:bg-brand-blue-dark lg:hidden"
        @click="$emit('click')"
    >
        <Plus class="size-6" />
    </button>
</template>

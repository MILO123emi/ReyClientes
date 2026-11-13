<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
const { dark, toggle } = useAppearance();
</script>
<template>
    <button
        type="button"
        class="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-white/10"
        :aria-label="dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        @click="toggle"
    >
        <Sun v-if="dark" class="size-5" /><Moon v-else class="size-5" />
    </button>
</template>

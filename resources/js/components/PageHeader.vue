<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>
<template>
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl leading-8 font-bold">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">
                {{ subtitle }}
            </p>
        </div>
        <div class="hidden shrink-0 lg:block"><slot /></div>
    </div>
</template>

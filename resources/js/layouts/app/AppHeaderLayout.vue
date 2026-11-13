<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50">
        <AppHeader />
        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col p-4 sm:p-6">
            <slot />
        </main>
        <Toaster />
    </div>
</template>

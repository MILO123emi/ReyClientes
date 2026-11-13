<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppBottomNav from '@/components/AppBottomNav.vue';
import AppHeader from '@/components/AppHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
const page = usePage();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <AppHeader />
        <main
            :class="
                page.props.auth.user.can.manageUsers
                    ? 'pb-[calc(152px+env(safe-area-inset-bottom))] lg:pb-6'
                    : 'pb-8'
            "
            class="mx-auto flex w-full max-w-6xl flex-1 flex-col p-4 sm:p-6"
        >
            <slot />
        </main>
        <AppBottomNav /><Toaster />
    </div>
</template>

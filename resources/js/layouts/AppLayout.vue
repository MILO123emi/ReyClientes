<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
import ConfirmPasswordDialog from '@/components/ConfirmPasswordDialog.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
        <ConfirmPasswordDialog />
    </AppLayout>
</template>

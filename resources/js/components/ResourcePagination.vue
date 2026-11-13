<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/cards';

defineProps<{
    links: Paginated<unknown>['links'];
    meta: Paginated<unknown>['meta'];
}>();
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        aria-label="Paginación"
        class="flex flex-wrap items-center justify-center gap-3 text-sm"
    >
        <Button v-if="links.prev" variant="outline" class="min-h-11" as-child
            ><Link :href="links.prev" preserve-scroll>Anterior</Link></Button
        >
        <Button v-else variant="outline" class="min-h-11" disabled
            >Anterior</Button
        >
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <Button v-if="links.next" variant="outline" class="min-h-11" as-child
            ><Link :href="links.next" preserve-scroll>Siguiente</Link></Button
        >
        <Button v-else variant="outline" class="min-h-11" disabled
            >Siguiente</Button
        >
    </nav>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ChevronDown } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import ResourcePagination from '@/components/ResourcePagination.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/audit';
import type { Paginated, RelationshipOption } from '@/types/cards';
import type { AuditEntry } from '@/types/management';

const props = defineProps<{
    logs: Paginated<AuditEntry>;
    actions: RelationshipOption[];
    filters: { action: string | null; from: string | null; to: string | null };
}>();
const form = useForm({
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const filtersOpen = ref(false);
function actionStyle(action: string): string {
    if (action.includes('creado') || action.includes('creada'))
        return 'bg-brand-blue-soft text-blue-soft-foreground';
    if (action.includes('eliminado') || action.includes('eliminada'))
        return 'bg-destructive-soft text-destructive-soft-foreground';
    return 'bg-muted text-muted-foreground';
}
function filter(): void {
    form.get(index.url(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
function clear(): void {
    form.action = '';
    form.from = '';
    form.to = '';
    filter();
}
</script>

<template>
    <div class="grid gap-5">
        <Head title="Registro de cambios" />
        <PageHeader
            title="Registro de cambios"
            subtitle="Actividad y seguridad de ReyPass"
        />
        <div class="rounded-2xl border bg-card shadow-card">
            <Button
                variant="ghost"
                class="min-h-12 w-full justify-between px-4 lg:hidden"
                :aria-expanded="filtersOpen"
                aria-controls="audit-filters"
                @click="filtersOpen = !filtersOpen"
                >Filtros<ChevronDown
                    class="size-4 transition-transform"
                    :class="filtersOpen ? 'rotate-180' : ''"
            /></Button>
            <form
                id="audit-filters"
                :class="filtersOpen ? 'grid' : 'hidden lg:grid'"
                class="items-start gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="filter"
            >
                <div class="grid gap-2">
                    <Label for="audit-action">Acción</Label
                    ><select
                        id="audit-action"
                        v-model="form.action"
                        class="min-h-11 w-full rounded-md border bg-card px-3 text-sm"
                        :disabled="form.processing"
                    >
                        <option value="">Todas las acciones</option>
                        <option
                            v-for="action in actions"
                            :key="action.value"
                            :value="action.value"
                        >
                            {{ action.label }}
                        </option></select
                    ><InputError :message="form.errors.action" />
                </div>
                <div class="grid gap-2">
                    <Label for="audit-from">Desde</Label
                    ><Input
                        id="audit-from"
                        v-model="form.from"
                        type="date"
                        class="min-h-11"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.from" />
                </div>
                <div class="grid gap-2">
                    <Label for="audit-to">Hasta</Label
                    ><Input
                        id="audit-to"
                        v-model="form.to"
                        type="date"
                        class="min-h-11"
                        :disabled="form.processing"
                    /><InputError :message="form.errors.to" />
                </div>
                <div class="flex flex-wrap gap-2 lg:pt-6">
                    <Button
                        type="submit"
                        class="min-h-11"
                        :disabled="form.processing"
                        ><Spinner v-if="form.processing" />Filtrar</Button
                    ><Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="form.processing"
                        @click="clear"
                        >Limpiar</Button
                    >
                </div>
            </form>
        </div>
        <div v-if="logs.data.length" class="grid gap-3 lg:hidden">
            <article
                v-for="(log, key) in logs.data"
                :key="key"
                class="min-w-0 rounded-2xl border bg-card p-4 shadow-card"
            >
                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-[13px] font-semibold"
                    :class="actionStyle(log.action_label)"
                    >{{ log.action_label }}</span
                >
                <p class="mt-3 text-sm leading-relaxed break-words">
                    {{ log.description }}
                </p>
                <p
                    class="mt-3 text-[13px] leading-relaxed break-words text-muted-foreground"
                >
                    {{ log.user }} · {{ formatDateTime(log.created_at) }} ·
                    {{ log.ip_address ?? '—' }}
                </p>
            </article>
        </div>
        <div
            v-if="logs.data.length"
            class="hidden rounded-2xl border bg-card shadow-card lg:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b bg-muted">
                    <tr>
                        <th class="p-4 font-semibold">Fecha</th>
                        <th class="p-4 font-semibold">Usuario</th>
                        <th class="p-4 font-semibold">Acción</th>
                        <th class="p-4 font-semibold">Descripción</th>
                        <th class="p-4 font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(log, key) in logs.data" :key="key">
                        <td class="p-4">
                            {{ formatDateTime(log.created_at) }}
                        </td>
                        <td class="p-4 break-words">{{ log.user }}</td>
                        <td class="p-4">{{ log.action_label }}</td>
                        <td class="p-4 break-words">{{ log.description }}</td>
                        <td class="p-4">{{ log.ip_address ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed bg-card p-12 text-center text-muted-foreground"
        >
            No hay cambios registrados para estos filtros.
        </p>
        <ResourcePagination :links="logs.links" :meta="logs.meta" />
    </div>
</template>

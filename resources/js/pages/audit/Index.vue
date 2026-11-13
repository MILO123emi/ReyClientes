<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
        <Head title="Auditoría" />
        <h1 class="text-2xl font-semibold text-primary">Auditoría</h1>
        <form
            class="grid items-start gap-4 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="filter"
        >
            <div class="grid gap-2">
                <Label for="audit-action">Acción</Label
                ><select
                    id="audit-action"
                    v-model="form.action"
                    class="min-h-11 w-full rounded-md border bg-white px-3 text-sm"
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
        <div
            v-if="logs.data.length"
            class="overflow-x-auto rounded-xl border bg-white"
        >
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="border-b bg-slate-50">
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
                        <td class="p-4 whitespace-nowrap">
                            {{ formatDateTime(log.created_at) }}
                        </td>
                        <td class="p-4">{{ log.user }}</td>
                        <td class="p-4">{{ log.action_label }}</td>
                        <td class="min-w-64 p-4">{{ log.description }}</td>
                        <td class="p-4">{{ log.ip_address ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed bg-white p-12 text-center text-muted-foreground"
        >
            No hay registros de auditoría para estos filtros.
        </p>
        <ResourcePagination :links="logs.links" :meta="logs.meta" />
    </div>
</template>

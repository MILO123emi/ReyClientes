<script setup lang="ts">
import { ChevronDown, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import AuthorizedPersonRow from '@/components/AuthorizedPersonRow.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { AuthorizedPerson, Client } from '@/types/cards';

const props = defineProps<{
    client: Client;
    canManage: boolean;
    matchedPersonIds?: number[];
    deleting?: boolean;
    deletingPerson?: number | null;
}>();
defineEmits<{
    edit: [client: Client];
    delete: [client: Client];
    addPerson: [client: Client];
    editPerson: [client: Client, person: AuthorizedPerson];
    deletePerson: [person: AuthorizedPerson];
}>();
const expanded = ref(false);
watch(
    () => props.matchedPersonIds,
    (ids) => {
        if (
            props.client.authorized_people.some((person) =>
                ids?.includes(person.id),
            )
        )
            expanded.value = true;
    },
    { immediate: true },
);
</script>

<template>
    <article class="rounded-xl border bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start gap-4 sm:flex-nowrap">
            <IdPhotoViewer :photo="client.photo" :name="client.full_name" />
            <div class="min-w-0 flex-1 basis-40">
                <h2 class="text-lg font-semibold break-words text-primary">
                    {{ client.full_name }}
                </h2>
                <dl class="mt-2 grid gap-1 text-sm">
                    <div>
                        <dt class="inline font-medium">Identidad:</dt>
                        <dd class="inline">{{ client.dni }}</dd>
                    </div>
                    <div>
                        <dt class="inline font-medium">Teléfono:</dt>
                        <dd class="inline">{{ client.phone }}</dd>
                    </div>
                </dl>
            </div>
            <div v-if="canManage" class="flex gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-11"
                    :aria-label="`Editar cliente ${client.full_name}`"
                    :disabled="deleting"
                    @click="$emit('edit', client)"
                    ><Pencil
                /></Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-11 text-destructive hover:text-destructive"
                    :aria-label="`Eliminar cliente ${client.full_name}`"
                    :disabled="deleting"
                    @click="$emit('delete', client)"
                    ><Spinner v-if="deleting" /><Trash2 v-else
                /></Button>
            </div>
        </div>
        <Collapsible v-model:open="expanded" class="mt-3">
            <CollapsibleTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="min-h-11 max-w-full justify-start text-left whitespace-normal"
                    ><ChevronDown
                        class="shrink-0 transition-transform"
                        :class="expanded ? 'rotate-180' : ''"
                    />Ver personas autorizadas ({{
                        client.people_count
                    }})</Button
                >
            </CollapsibleTrigger>
            <CollapsibleContent
                class="mt-3 grid gap-3 rounded-lg border bg-slate-50 p-3"
            >
                <AuthorizedPersonRow
                    v-for="person in client.authorized_people"
                    :key="person.id"
                    :person="person"
                    :can-manage="canManage"
                    :highlighted="matchedPersonIds?.includes(person.id)"
                    :deleting="deletingPerson === person.id"
                    @edit="$emit('editPerson', client, $event)"
                    @delete="$emit('deletePerson', $event)"
                />
                <p
                    v-if="client.authorized_people.length === 0"
                    class="py-2 text-sm text-muted-foreground"
                >
                    Este cliente aún no tiene personas autorizadas.
                </p>
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    class="min-h-11 w-fit max-w-full justify-start text-left whitespace-normal"
                    @click="$emit('addPerson', client)"
                    ><Plus />Agregar persona autorizada</Button
                >
            </CollapsibleContent>
        </Collapsible>
    </article>
</template>

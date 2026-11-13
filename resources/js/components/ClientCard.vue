<script setup lang="ts">
import {
    ChevronDown,
    Pencil,
    Plus,
    Trash2,
    EllipsisVertical,
    IdCard,
    Phone,
} from '@lucide/vue';
import { ref } from 'vue';
import RoleBadge from '@/components/RoleBadge.vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import AuthorizedPersonRow from '@/components/AuthorizedPersonRow.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AuthorizedPerson, Client } from '@/types/cards';

defineProps<{
    client: Client;
    canManage: boolean;
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
</script>

<template>
    <article
        class="min-w-0 rounded-2xl border border-l-4 border-l-primary bg-card p-4 shadow-card"
    >
        <RoleBadge role="owner" class="mb-3" />
        <div class="relative flex items-start gap-3">
            <IdPhotoViewer :photo="client.photo" :name="client.full_name" />
            <div class="min-w-0 flex-1">
                <h2
                    :class="canManage ? 'pr-6' : ''"
                    class="line-clamp-2 text-lg leading-snug font-semibold break-words"
                >
                    {{ client.full_name }}
                </h2>
                <div
                    class="mt-2 grid gap-1.5 text-[15px] text-muted-foreground"
                >
                    <p class="flex items-center gap-1.5 tabular-nums">
                        <IdCard class="size-4 shrink-0" /><span>{{
                            client.dni
                        }}</span>
                    </p>
                    <a
                        :href="`tel:${client.phone}`"
                        class="flex min-h-11 items-center gap-1.5 tabular-nums"
                        ><Phone class="size-4 shrink-0" />{{ client.phone }}</a
                    >
                </div>
            </div>
            <DropdownMenu v-if="canManage">
                <DropdownMenuTrigger as-child
                    ><Button
                        variant="ghost"
                        size="icon"
                        class="absolute -top-2 -right-2 size-11"
                        :aria-label="`Acciones de ${client.full_name}`"
                        :disabled="deleting"
                        ><Spinner v-if="deleting" /><EllipsisVertical
                            v-else /></Button
                ></DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        class="min-h-11"
                        @select="$emit('edit', client)"
                        ><Pencil />Editar cliente</DropdownMenuItem
                    >
                    <DropdownMenuItem
                        class="min-h-11"
                        @select="$emit('addPerson', client)"
                        ><Plus />Agregar persona autorizada</DropdownMenuItem
                    >
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="min-h-11 text-destructive-soft-foreground"
                        @select="$emit('delete', client)"
                        ><Trash2 />Eliminar</DropdownMenuItem
                    >
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <Collapsible v-model:open="expanded" class="mt-3">
            <CollapsibleTrigger as-child
                ><Button
                    variant="ghost"
                    class="min-h-12 w-full justify-between gap-2 rounded-xl bg-muted px-3 text-[13px] hover:bg-muted"
                    ><span class="text-left whitespace-normal"
                        >Personas autorizadas a usar esta tarjeta</span
                    ><span
                        class="ml-auto rounded-full bg-accent px-2 py-0.5 text-accent-foreground"
                        >{{ client.people_count }}</span
                    ><ChevronDown
                        class="size-4 shrink-0 transition-transform"
                        :class="expanded ? 'rotate-180' : ''" /></Button
            ></CollapsibleTrigger>
            <CollapsibleContent class="mt-2 grid divide-y">
                <AuthorizedPersonRow
                    v-for="person in client.authorized_people"
                    :key="person.id"
                    :person="person"
                    :can-manage="canManage"
                    :deleting="deletingPerson === person.id"
                    @edit="$emit('editPerson', client, $event)"
                    @delete="$emit('deletePerson', $event)"
                />
                <p
                    v-if="client.authorized_people.length === 0"
                    class="py-4 text-sm text-muted-foreground"
                >
                    Sin personas autorizadas
                </p>
                <div v-if="canManage" class="pt-3">
                    <Button
                        variant="outline"
                        class="min-h-11 w-full border-dashed border-link px-2 text-[13px] whitespace-normal text-link dark:border-link dark:bg-transparent"
                        @click="$emit('addPerson', client)"
                        ><Plus class="shrink-0" />Agregar persona
                        autorizada</Button
                    >
                </div>
            </CollapsibleContent>
        </Collapsible>
    </article>
</template>

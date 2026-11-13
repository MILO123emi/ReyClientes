<script setup lang="ts">
import { EllipsisVertical, Pencil, Trash2 } from '@lucide/vue';
import RoleBadge from '@/components/RoleBadge.vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AuthorizedPerson } from '@/types/cards';

defineProps<{
    person: AuthorizedPerson;
    canManage: boolean;
    deleting?: boolean;
}>();
defineEmits<{
    edit: [person: AuthorizedPerson];
    delete: [person: AuthorizedPerson];
}>();
</script>

<template>
    <div
        class="relative grid min-w-0 grid-cols-[64px_minmax(0,1fr)] items-start gap-2 py-3"
    >
        <IdPhotoViewer :photo="person.photo" :name="person.full_name" compact />
        <div class="min-w-0" :class="canManage ? 'pr-9' : ''">
            <p class="text-sm font-semibold break-words">
                {{ person.full_name }}
            </p>
            <RoleBadge role="authorized" class="mt-1" />
            <Badge class="mt-1 bg-accent text-[13px] text-accent-foreground">{{
                person.relationship_label
            }}</Badge>
            <p
                class="mt-2 text-[13px] leading-relaxed text-muted-foreground tabular-nums"
            >
                {{ person.dni }} ·
                <a
                    :href="`tel:${person.phone}`"
                    class="inline-flex min-h-11 items-center"
                    >{{ person.phone }}</a
                >
            </p>
        </div>
        <DropdownMenu v-if="canManage"
            ><DropdownMenuTrigger as-child
                ><Button
                    variant="ghost"
                    size="icon"
                    class="absolute top-1 right-0 size-11"
                    :aria-label="`Acciones de ${person.full_name}`"
                    :disabled="deleting"
                    ><Spinner v-if="deleting" /><EllipsisVertical
                        v-else /></Button></DropdownMenuTrigger
            ><DropdownMenuContent align="end"
                ><DropdownMenuItem
                    class="min-h-11"
                    @select="$emit('edit', person)"
                    ><Pencil />Editar</DropdownMenuItem
                ><DropdownMenuItem
                    class="min-h-11 text-destructive-soft-foreground"
                    @select="$emit('delete', person)"
                    ><Trash2 />Eliminar</DropdownMenuItem
                ></DropdownMenuContent
            ></DropdownMenu
        >
    </div>
</template>

<script setup lang="ts">
import { EllipsisVertical, IdCard, Pencil, Phone, Trash2 } from '@lucide/vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import type { AuthorizedPerson } from '@/types/cards';

defineProps<{
    person: AuthorizedPerson;
    canManage: boolean;
    deleting?: boolean;
    highlighted?: boolean;
}>();
defineEmits<{
    edit: [person: AuthorizedPerson];
    delete: [person: AuthorizedPerson];
}>();
</script>

<template>
    <article
        class="min-w-0 rounded-2xl border border-t-4 border-t-brand-yellow bg-card p-4 shadow-card"
        :class="highlighted ? 'ring-4 ring-brand-yellow' : ''"
    >
        <IdPhotoViewer :photo="person.photo" :name="person.full_name" large />
        <div class="relative mt-4 min-w-0">
            <RoleBadge role="authorized" />
            <h3
                class="mt-2 text-xl leading-snug font-semibold break-words"
                :class="canManage ? 'pr-10' : ''"
            >
                {{ person.full_name }}
            </h3>
            <Badge class="mt-2 bg-accent text-sm text-accent-foreground">{{
                person.relationship_label
            }}</Badge>
            <div class="mt-3 grid gap-1 text-base tabular-nums">
                <p class="flex items-center gap-2">
                    <IdCard
                        class="size-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    /><span>{{ person.dni }}</span>
                </p>
                <a
                    :href="`tel:${person.phone}`"
                    class="flex min-h-11 w-fit items-center gap-2"
                    ><Phone
                        class="size-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />{{ person.phone }}</a
                >
            </div>
            <DropdownMenu v-if="canManage"
                ><DropdownMenuTrigger as-child
                    ><Button
                        variant="ghost"
                        size="icon"
                        class="absolute -top-2 -right-2 size-11"
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
    </article>
</template>

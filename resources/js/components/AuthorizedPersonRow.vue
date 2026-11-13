<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { AuthorizedPerson } from '@/types/cards';

defineProps<{
    person: AuthorizedPerson;
    canManage: boolean;
    highlighted?: boolean;
    deleting?: boolean;
}>();
defineEmits<{
    edit: [person: AuthorizedPerson];
    delete: [person: AuthorizedPerson];
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-start gap-3 rounded-lg bg-white p-3"
        :class="highlighted ? 'ring-2 ring-brand-yellow' : ''"
    >
        <IdPhotoViewer :photo="person.photo" :name="person.full_name" />
        <div class="min-w-0 flex-1 basis-36">
            <p class="font-medium break-words">{{ person.full_name }}</p>
            <Badge class="mt-1 bg-accent text-accent-foreground">{{
                person.relationship_label
            }}</Badge>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ person.dni }} · {{ person.phone }}
            </p>
        </div>
        <div v-if="canManage" class="flex gap-1">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-11"
                :aria-label="`Editar persona autorizada ${person.full_name}`"
                :disabled="deleting"
                @click="$emit('edit', person)"
                ><Pencil
            /></Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-11 text-destructive hover:text-destructive"
                :aria-label="`Eliminar persona autorizada ${person.full_name}`"
                :disabled="deleting"
                @click="$emit('delete', person)"
                ><Spinner v-if="deleting" /><Trash2 v-else
            /></Button>
        </div>
    </div>
</template>

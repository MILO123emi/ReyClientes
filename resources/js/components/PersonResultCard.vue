<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, IdCard, Phone } from '@lucide/vue';
import ClientCard from '@/components/ClientCard.vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/clients';
import type { AuthorizedPerson, Client, SearchResult } from '@/types/cards';

defineProps<{
    result: SearchResult;
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
</script>

<template>
    <div class="min-w-0">
        <ClientCard
            v-if="result.role === 'owner'"
            :client="result.client"
            :can-manage="canManage"
            :deleting="deleting"
            :deleting-person="deletingPerson"
            @edit="$emit('edit', $event)"
            @delete="$emit('delete', $event)"
            @add-person="$emit('addPerson', $event)"
            @edit-person="
                (client, person) => $emit('editPerson', client, person)
            "
            @delete-person="$emit('deletePerson', $event)"
        />
        <article
            v-else-if="result.person"
            class="min-w-0 rounded-2xl border border-l-4 border-l-brand-yellow bg-card p-4 shadow-card"
        >
            <RoleBadge role="authorized" class="mb-3" />
            <div class="flex items-start gap-3">
                <IdPhotoViewer
                    :photo="result.person.photo"
                    :name="result.person.full_name"
                />
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg leading-snug font-semibold break-words">
                        {{ result.person.full_name }}
                    </h2>
                    <div
                        class="mt-2 grid gap-1.5 text-[15px] text-muted-foreground tabular-nums"
                    >
                        <p class="flex items-center gap-1.5">
                            <IdCard
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            /><span>{{ result.person.dni }}</span>
                        </p>
                        <a
                            :href="`tel:${result.person.phone}`"
                            class="flex min-h-11 items-center gap-1.5"
                            ><Phone
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />{{ result.person.phone }}</a
                        >
                    </div>
                </div>
            </div>
            <div class="mt-3 rounded-xl bg-muted p-3">
                <p class="mb-2 text-[13px] text-muted-foreground">
                    Autorizada por
                </p>
                <div class="flex items-start gap-3">
                    <IdPhotoViewer
                        :photo="result.client.photo"
                        :name="result.client.full_name"
                        compact
                    />
                    <div class="min-w-0">
                        <p class="text-[15px] font-semibold break-words">
                            {{ result.client.full_name }}
                        </p>
                        <p
                            class="mt-1 text-[13px] text-muted-foreground tabular-nums"
                        >
                            {{ result.client.dni }}
                        </p>
                        <Badge
                            class="mt-2 bg-accent text-[13px] text-accent-foreground"
                            >{{ result.person.relationship_label }}</Badge
                        >
                    </div>
                </div>
            </div>
            <Button
                variant="outline"
                class="mt-3 min-h-11 w-full justify-between gap-2 border-primary whitespace-normal text-link dark:bg-transparent"
                as-child
                ><Link
                    :href="
                        show(result.client.id, {
                            query: { persona: result.person.id },
                        }).url
                    "
                    >Ver en grande la tarjeta de
                    {{ result.client.full_name.split(' ')[0] }}
                    <ChevronRight class="size-4 shrink-0" /></Link
            ></Button>
        </article>
    </div>
</template>

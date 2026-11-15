<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    IdCard,
    Pencil,
    Phone,
    Plus,
    Trash2,
    UserCheck,
} from '@lucide/vue';
import { computed, nextTick, onMounted } from 'vue';
import AuthorizedPersonCard from '@/components/AuthorizedPersonCard.vue';
import AuthorizedPersonFormDialog from '@/components/AuthorizedPersonFormDialog.vue';
import ClientFormDialog from '@/components/ClientFormDialog.vue';
import IdPhotoViewer from '@/components/IdPhotoViewer.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useClientActions } from '@/composables/useClientActions';
import { index } from '@/routes/clients';
import type { Client, RelationshipOption } from '@/types/cards';

const props = defineProps<{
    client: { data: Client };
    relationships: RelationshipOption[];
}>();
const client = computed(() => props.client.data);
const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manageCards);
const firstName = computed(() => client.value.full_name.split(' ')[0]);
const highlightedId = computed(() => {
    const id = Number(
        new URLSearchParams(page.url.split('?')[1] ?? '').get('persona'),
    );

    return Number.isInteger(id) && id > 0 ? id : null;
});
const {
    formOpen,
    editing,
    deleting,
    personFormOpen,
    personClient,
    editingPerson,
    deletingPerson,
    edit,
    editPerson,
    removePerson,
    remove,
} = useClientActions();

function goBack(): void {
    if (window.history.length > 1) window.history.back();
    else router.visit(index().url);
}

onMounted(async () => {
    if (highlightedId.value === null) return;
    await nextTick();
    document
        .getElementById(`person-${highlightedId.value}`)
        ?.scrollIntoView({ block: 'center' });
});
</script>

<template>
    <div class="grid gap-6">
        <Head :title="client.full_name" />
        <div>
            <Button
                variant="ghost"
                class="-ml-2 min-h-11 text-[15px] text-link"
                @click="goBack"
                ><ArrowLeft />Volver a clientes</Button
            >
        </div>
        <section
            class="min-w-0 rounded-3xl border border-t-4 border-t-primary bg-card p-4 shadow-card sm:p-6"
            aria-labelledby="owner-name"
        >
            <div
                class="grid gap-5 md:grid-cols-[minmax(0,380px)_minmax(0,1fr)] md:items-center md:gap-8"
            >
                <IdPhotoViewer
                    :photo="client.photo"
                    :name="client.full_name"
                    large
                />
                <div class="min-w-0">
                    <RoleBadge role="owner" />
                    <h1
                        id="owner-name"
                        class="mt-3 text-3xl leading-tight font-bold break-words sm:text-4xl"
                    >
                        {{ client.full_name }}
                    </h1>
                    <dl class="mt-4 grid gap-3">
                        <div class="flex items-center gap-3">
                            <IdCard
                                class="size-6 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-[13px] text-muted-foreground">
                                    Identidad
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    {{ client.dni }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Phone
                                class="size-6 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-[13px] text-muted-foreground">
                                    Teléfono
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    <a
                                        :href="`tel:${client.phone}`"
                                        class="inline-flex min-h-11 items-center"
                                        >{{ client.phone }}</a
                                    >
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <div v-if="canManage" class="mt-4 flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            class="min-h-11"
                            @click="edit(client)"
                            ><Pencil />Editar cliente</Button
                        >
                        <Button
                            variant="outline"
                            class="min-h-11 text-destructive-soft-foreground"
                            :disabled="deleting === client.id"
                            @click="remove(client)"
                            ><Spinner v-if="deleting === client.id" /><Trash2
                                v-else
                            />Eliminar</Button
                        >
                    </div>
                </div>
            </div>
        </section>
        <section class="grid min-w-0 gap-4" aria-labelledby="people-title">
            <div>
                <h2
                    id="people-title"
                    class="flex flex-wrap items-center gap-2 text-xl leading-snug font-bold sm:text-2xl"
                >
                    Personas autorizadas a usar esta tarjeta
                    <span
                        class="rounded-full bg-accent px-3 py-0.5 text-base text-accent-foreground"
                        >{{ client.authorized_people.length }}</span
                    >
                </h2>
                <p class="mt-1 text-[15px] text-muted-foreground">
                    {{ firstName }} les da permiso de usar su tarjeta.
                </p>
            </div>
            <div
                v-if="client.authorized_people.length"
                class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <AuthorizedPersonCard
                    v-for="person in client.authorized_people"
                    :id="`person-${person.id}`"
                    :key="person.id"
                    :person="person"
                    :can-manage="canManage"
                    :deleting="deletingPerson === person.id"
                    :highlighted="highlightedId === person.id"
                    @edit="editPerson(client, $event)"
                    @delete="removePerson"
                />
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed bg-card px-5 py-10 text-center"
            >
                <div
                    class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-accent text-accent-foreground"
                >
                    <UserCheck class="size-7" />
                </div>
                <p class="font-semibold">
                    {{ firstName }} no ha autorizado a nadie
                </p>
                <p class="mt-1 text-[15px] text-muted-foreground">
                    Solo {{ firstName }} puede usar esta tarjeta.
                </p>
            </div>
            <Button
                v-if="canManage"
                variant="outline"
                class="min-h-12 w-full border-dashed border-link text-[15px] whitespace-normal text-link sm:w-fit dark:border-link dark:bg-transparent"
                @click="editPerson(client)"
                ><Plus class="shrink-0" />Agregar persona autorizada</Button
            >
        </section>
        <ClientFormDialog v-model:open="formOpen" :client="editing" />
        <AuthorizedPersonFormDialog
            v-model:open="personFormOpen"
            :client="personClient"
            :person="editingPerson"
            :relationships="relationships"
        />
    </div>
</template>

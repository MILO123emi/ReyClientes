<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ClientCard from '@/components/ClientCard.vue';
import ClientFormDialog from '@/components/ClientFormDialog.vue';
import AuthorizedPersonFormDialog from '@/components/AuthorizedPersonFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { destroy, index, search } from '@/routes/clients';
import { destroy as destroyPerson } from '@/routes/people';
import type {
    AuthorizedPerson,
    Client,
    ClientFilters,
    Paginated,
    RelationshipOption,
} from '@/types/cards';

const props = defineProps<{
    clients: Paginated<Client>;
    relationships: RelationshipOption[];
    filters: ClientFilters;
    matchedPersonIds: number[];
}>();
const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manageCards);
const formOpen = ref(false);
const editing = ref<Client | null>(null);
const deleting = ref<number | null>(null);
const personFormOpen = ref(false);
const personClient = ref<Client | null>(null);
const editingPerson = ref<AuthorizedPerson | null>(null);
const deletingPerson = ref<number | null>(null);
const query = ref(props.filters.q);
const searching = ref(false);
const searchError = ref('');
const searchLabel = computed(() =>
    props.filters.dni
        ? `Identidad ${props.filters.dni.masked}`
        : props.filters.q,
);
let timer: ReturnType<typeof setTimeout> | undefined;
let cancelSearch: (() => void) | null = null;
let searchGeneration = 0;
let syncingQuery = false;

function setQuery(value: string): void {
    syncingQuery = true;
    query.value = value;
    syncingQuery = false;
}

function runSearch(value: string, generation: number): void {
    const text = value.trim();
    const digits = text.replace(/\D/g, '');

    if (digits.length > 13) {
        searchError.value = 'La identidad debe tener 13 dígitos.';
        return;
    }

    searching.value = true;
    const options = {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onCancelToken: (token: { cancel: () => void }) => {
            cancelSearch = () => token.cancel();
        },
        onError: (errors: Record<string, string>) => {
            if (generation === searchGeneration)
                searchError.value = errors.dni ?? '';
        },
        onFinish: () => {
            if (generation === searchGeneration) {
                searching.value = false;
                cancelSearch = null;
            }
        },
    };

    if (digits.length === 13) {
        router.post(
            search().url,
            { dni: digits },
            {
                ...options,
                onSuccess: () => {
                    if (generation === searchGeneration) setQuery('');
                },
            },
        );
    } else {
        router.get(index().url, text ? { q: text } : {}, options);
    }
}

function queueSearch(value: string, immediate = false): void {
    searchGeneration += 1;
    clearTimeout(timer);
    cancelSearch?.();
    cancelSearch = null;
    searching.value = false;
    searchError.value = '';
    const generation = searchGeneration;
    if (immediate) runSearch(value, generation);
    else timer = setTimeout(() => runSearch(value, generation), 300);
}

function clearSearch(): void {
    setQuery('');
    queueSearch('', true);
}

watch(
    query,
    (value) => {
        if (!syncingQuery) queueSearch(value);
    },
    { flush: 'sync' },
);
watch(
    () => props.filters,
    (filters) => {
        clearTimeout(timer);
        setQuery(filters.dni ? '' : filters.q);
    },
);
onBeforeUnmount(() => {
    clearTimeout(timer);
    cancelSearch?.();
});

function edit(client: Client | null): void {
    editing.value = client;
    formOpen.value = true;
}

function editPerson(
    client: Client,
    person: AuthorizedPerson | null = null,
): void {
    personClient.value = client;
    editingPerson.value = person;
    personFormOpen.value = true;
}

async function removePerson(person: AuthorizedPerson): Promise<void> {
    if (deletingPerson.value !== null) return;
    deletingPerson.value = person.id;
    try {
        await submitWithPassword(
            'Confirma la eliminación de la persona autorizada',
            (password, callbacks) => {
                router.delete(destroyPerson.url(person.id), {
                    ...callbacks,
                    data: { current_password: password },
                    preserveScroll: true,
                });
            },
            {
                destructive: true,
                description: `Vas a eliminar a ${person.full_name}. Esta acción no se puede deshacer.`,
            },
        );
    } finally {
        deletingPerson.value = null;
    }
}
async function remove(client: Client): Promise<void> {
    if (deleting.value !== null) return;
    deleting.value = client.id;
    try {
        await submitWithPassword(
            'Confirma la eliminación del cliente',
            (password, callbacks) => {
                router.delete(destroy.url(client.id), {
                    ...callbacks,
                    data: { current_password: password },
                    preserveScroll: true,
                });
            },
            {
                destructive: true,
                description: `Vas a eliminar a ${client.full_name} y sus ${client.people_count} personas autorizadas. Esta acción no se puede deshacer.`,
            },
        );
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <div class="grid gap-5">
        <Head title="Clientes" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-primary">Clientes</h1>
            <Button v-if="canManage" class="min-h-11" @click="edit(null)"
                ><Plus />Cliente</Button
            >
        </div>
        <form
            class="relative"
            role="search"
            @submit.prevent="queueSearch(query, true)"
        >
            <Search
                class="pointer-events-none absolute top-3 left-3 size-5 text-muted-foreground"
            /><Input
                v-model="query"
                type="search"
                maxlength="100"
                aria-label="Buscar por nombre, identidad o teléfono"
                placeholder="Buscar por nombre, identidad o teléfono"
                class="min-h-11 pl-10"
                :aria-busy="searching"
                :aria-invalid="Boolean(searchError)"
                :aria-describedby="
                    searchError ? 'client-search-error' : undefined
                "
            />
            <LoaderCircle
                v-if="searching"
                class="pointer-events-none absolute top-3 right-3 size-5 animate-spin text-primary"
                aria-label="Buscando"
            />
        </form>
        <InputError id="client-search-error" :message="searchError" />
        <div v-if="filters.dni" class="flex flex-wrap items-center gap-2">
            <span
                class="rounded-md bg-accent px-3 py-2 text-sm text-accent-foreground"
                >Identidad {{ filters.dni.masked }}</span
            >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-11"
                aria-label="Quitar filtro de identidad"
                @click="clearSearch"
                ><X
            /></Button>
        </div>
        <div v-if="clients.data.length" class="grid gap-4">
            <ClientCard
                v-for="client in clients.data"
                :key="client.id"
                :client="client"
                :can-manage="canManage"
                :matched-person-ids="matchedPersonIds"
                :deleting="deleting === client.id"
                :deleting-person="deletingPerson"
                @edit="edit"
                @delete="remove"
                @add-person="editPerson"
                @edit-person="editPerson"
                @delete-person="removePerson"
            />
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed bg-white px-5 py-14 text-center text-muted-foreground"
        >
            <p>
                {{
                    searchLabel
                        ? `No se encontraron resultados para "${searchLabel}".`
                        : canManage
                          ? 'Aún no hay clientes. Agrega el primero.'
                          : 'Aún no hay clientes registrados.'
                }}
            </p>
            <Button
                v-if="canManage && !searchLabel"
                variant="outline"
                class="mt-4 min-h-11"
                @click="edit(null)"
                ><Plus />Agregar cliente</Button
            >
        </div>
        <nav
            v-if="clients.meta.last_page > 1"
            aria-label="Paginación"
            class="flex flex-wrap items-center justify-center gap-3 text-sm"
        >
            <Button
                variant="outline"
                class="min-h-11"
                :disabled="!clients.links.prev"
                as-child
                ><Link
                    v-if="clients.links.prev"
                    :href="clients.links.prev"
                    preserve-scroll
                    >Anterior</Link
                ><span v-else>Anterior</span></Button
            >
            <span
                >Página {{ clients.meta.current_page }} de
                {{ clients.meta.last_page }}</span
            >
            <Button
                variant="outline"
                class="min-h-11"
                :disabled="!clients.links.next"
                as-child
                ><Link
                    v-if="clients.links.next"
                    :href="clients.links.next"
                    preserve-scroll
                    >Siguiente</Link
                ><span v-else>Siguiente</span></Button
            >
        </nav>
        <ClientFormDialog v-model:open="formOpen" :client="editing" />
        <AuthorizedPersonFormDialog
            v-model:open="personFormOpen"
            :client="personClient"
            :person="editingPerson"
            :relationships="relationships"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ContactRound, LoaderCircle, Plus, Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import Fab from '@/components/Fab.vue';
import { Skeleton } from '@/components/ui/skeleton';
import ClientCard from '@/components/ClientCard.vue';
import PersonResultCard from '@/components/PersonResultCard.vue';
import RoleFilter from '@/components/RoleFilter.vue';
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
    SearchResult,
    SearchCounts,
} from '@/types/cards';

const props = defineProps<{
    clients: Paginated<Client> | null;
    results: Paginated<SearchResult> | null;
    counts: SearchCounts;
    relationships: RelationshipOption[];
    filters: ClientFilters;
}>();
const pagination = computed(() => props.results ?? props.clients);
const resultGroups = computed(() =>
    [
        {
            role: 'owner',
            label: 'Dueños de tarjeta',
            results:
                props.results?.data.filter(
                    (result) => result.role === 'owner',
                ) ?? [],
        },
        {
            role: 'authorized',
            label: 'Personas autorizadas',
            results:
                props.results?.data.filter(
                    (result) => result.role === 'authorized',
                ) ?? [],
        },
    ].filter((group) => group.results.length),
);
const emptyMessage = computed(() => {
    const subject =
        props.filters.role === 'duenos'
            ? 'dueños'
            : props.filters.role === 'autorizados'
              ? 'personas autorizadas'
              : 'resultados';
    if (searchLabel.value)
        return `No se encontraron ${subject} para "${searchLabel.value}".`;
    if (props.filters.role !== 'todos') return `No se encontraron ${subject}.`;
    return canManage.value
        ? 'Aún no hay clientes. Agrega el primero.'
        : 'Aún no hay clientes registrados.';
});
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

    const selectedRole = props.filters.role;
    if (digits.length === 13) {
        router.post(
            search().url,
            { dni: digits },
            {
                ...options,
                onSuccess: () => {
                    if (generation === searchGeneration) {
                        setQuery('');
                        if (selectedRole !== 'todos') {
                            router.get(
                                index().url,
                                { dni: 1, rol: selectedRole },
                                options,
                            );
                        }
                    }
                },
            },
        );
    } else {
        router.get(
            index().url,
            {
                ...(text ? { q: text } : {}),
                ...(props.filters.role !== 'todos'
                    ? { rol: props.filters.role }
                    : {}),
            },
            options,
        );
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

function cancelPendingSearch(): void {
    searchGeneration += 1;
    clearTimeout(timer);
    cancelSearch?.();
    cancelSearch = null;
    searching.value = false;
    searchError.value = '';
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
        <PageHeader
            title="Clientes"
            :subtitle="
                results
                    ? `${results.meta.total} resultados`
                    : `${clients?.meta.total ?? 0} ${clients?.meta.total === 1 ? 'cliente' : 'clientes'}`
            "
            ><Button v-if="canManage" class="min-h-12" @click="edit(null)"
                ><Plus />Agregar cliente</Button
            ></PageHeader
        >
        <Fab v-if="canManage" label="Agregar cliente" @click="edit(null)" />
        <form
            class="sticky top-[calc(67px+env(safe-area-inset-top))] z-20 -mx-1 bg-background/95 px-1 py-2 backdrop-blur"
            role="search"
            @submit.prevent="queueSearch(query, true)"
        >
            <Search
                class="pointer-events-none absolute top-[22px] left-4 size-5 text-muted-foreground"
            /><Input
                v-model="query"
                type="search"
                maxlength="100"
                aria-label="Buscar por nombre, identidad o teléfono"
                placeholder="Buscar por nombre, identidad o teléfono"
                class="h-12 rounded-full bg-card pr-12 pl-10"
                :aria-busy="searching"
                :aria-invalid="Boolean(searchError)"
                :aria-describedby="
                    searchError ? 'client-search-error' : undefined
                "
            />
            <LoaderCircle
                v-if="searching"
                class="pointer-events-none absolute top-[22px] right-4 size-5 animate-spin text-primary"
                aria-label="Buscando"
            />
            <Button
                v-if="query && !searching"
                type="button"
                variant="ghost"
                size="icon"
                class="absolute top-2.5 right-2 size-11 rounded-full"
                aria-label="Limpiar búsqueda"
                @click="clearSearch"
                ><X
            /></Button>
            <RoleFilter
                :filters="filters"
                :counts="counts"
                @before-navigate="cancelPendingSearch"
                @busy="searching = $event"
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
        <div
            v-if="searching"
            class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3"
            aria-label="Buscando clientes"
        >
            <Skeleton v-for="n in 3" :key="n" class="h-56 rounded-2xl" />
        </div>
        <div v-else-if="results?.data.length" class="grid gap-5">
            <section
                v-for="group in resultGroups"
                :key="group.role"
                class="min-w-0"
            >
                <h2
                    v-if="resultGroups.length > 1"
                    class="mb-3 flex items-center gap-3 text-sm font-semibold text-muted-foreground"
                >
                    {{ group.label }}<span class="h-px flex-1 bg-border" />
                </h2>
                <div class="grid items-start gap-4 lg:grid-cols-2">
                    <PersonResultCard
                        v-for="result in group.results"
                        :key="result.key"
                        :result="result"
                        :can-manage="canManage"
                        :deleting="deleting === result.client.id"
                        :deleting-person="deletingPerson"
                        @edit="edit"
                        @delete="remove"
                        @add-person="editPerson"
                        @edit-person="editPerson"
                        @delete-person="removePerson"
                    />
                </div>
            </section>
        </div>
        <div
            v-else-if="clients?.data.length"
            class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <ClientCard
                v-for="client in clients.data"
                :key="client.id"
                :client="client"
                :can-manage="canManage"
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
            class="rounded-xl border border-dashed bg-card px-5 py-14 text-center text-muted-foreground"
        >
            <div
                class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-brand-blue-soft text-blue-soft-foreground"
            >
                <ContactRound class="size-7" />
            </div>
            <p class="font-semibold text-foreground">
                {{ emptyMessage }}
            </p>
            <Link
                v-if="filters.role !== 'todos'"
                :href="
                    index({
                        query: filters.dni
                            ? { dni: 1 }
                            : filters.q
                              ? { q: filters.q }
                              : {},
                    }).url
                "
                class="mt-3 inline-flex min-h-11 items-center text-link underline"
                preserve-state
                replace
                >Buscar en todos</Link
            >
            <Button
                v-if="canManage && !searchLabel && filters.role === 'todos'"
                variant="outline"
                class="mt-4 min-h-11"
                @click="edit(null)"
                ><Plus />Agregar cliente</Button
            >
        </div>
        <nav
            v-if="pagination && pagination.meta.last_page > 1"
            aria-label="Paginación"
            class="flex flex-wrap items-center justify-center gap-3 text-sm"
        >
            <Button
                variant="outline"
                class="min-h-11"
                :disabled="!pagination!.links.prev"
                as-child
                ><Link
                    v-if="pagination!.links.prev"
                    :href="pagination!.links.prev"
                    preserve-scroll
                    >Anterior</Link
                ><span v-else>Anterior</span></Button
            >
            <span
                >Página {{ pagination!.meta.current_page }} de
                {{ pagination!.meta.last_page }}</span
            >
            <Button
                variant="outline"
                class="min-h-11"
                :disabled="!pagination!.links.next"
                as-child
                ><Link
                    v-if="pagination!.links.next"
                    :href="pagination!.links.next"
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

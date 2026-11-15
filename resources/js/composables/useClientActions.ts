import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { destroy } from '@/routes/clients';
import { destroy as destroyPerson } from '@/routes/people';
import type { AuthorizedPerson, Client } from '@/types/cards';

/** State and handlers shared by the pages that manage clients and their authorized people. */
export function useClientActions() {
    const formOpen = ref(false);
    const editing = ref<Client | null>(null);
    const deleting = ref<number | null>(null);
    const personFormOpen = ref(false);
    const personClient = ref<Client | null>(null);
    const editingPerson = ref<AuthorizedPerson | null>(null);
    const deletingPerson = ref<number | null>(null);

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

    return {
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
    };
}

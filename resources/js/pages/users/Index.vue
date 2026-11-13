<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { KeyRound, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import Fab from '@/components/Fab.vue';
import ResourcePagination from '@/components/ResourcePagination.vue';
import UserFormDialog from '@/components/UserFormDialog.vue';
import SetUserPasswordDialog from '@/components/SetUserPasswordDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { destroy } from '@/routes/users';
import type { Paginated } from '@/types/cards';
import type { ManagedUser } from '@/types/management';

defineProps<{ users: Paginated<ManagedUser>; passwordRules: string }>();
const createOpen = ref(false);
const passwordOpen = ref(false);
const selectedUser = ref<ManagedUser | null>(null);
const busy = ref<number | null>(null);

function assignPassword(user: ManagedUser): void {
    selectedUser.value = user;
    passwordOpen.value = true;
}
async function remove(user: ManagedUser): Promise<void> {
    if (busy.value !== null || user.is_owner) return;
    busy.value = user.id;
    try {
        await submitWithPassword(
            'Confirma la eliminación del usuario',
            (password, callbacks) => {
                router.delete(destroy.url(user.id), {
                    ...callbacks,
                    data: { current_password: password },
                    preserveScroll: true,
                });
            },
            {
                destructive: true,
                description: `Vas a eliminar a ${user.name}. Esta acción no se puede deshacer.`,
            },
        );
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="grid gap-5">
        <Head title="Usuarios" />
        <PageHeader
            title="Usuarios"
            :subtitle="`${users.meta.total} cuentas del equipo`"
            ><Button class="min-h-12" @click="createOpen = true"
                ><Plus />Nuevo empleado</Button
            ></PageHeader
        >
        <Fab label="Nuevo empleado" @click="createOpen = true" />
        <div class="grid gap-4 md:grid-cols-2 lg:hidden">
            <article
                v-for="user in users.data"
                :key="user.id"
                class="min-w-0 rounded-2xl border bg-card p-4 shadow-card"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue-soft font-bold text-blue-soft-foreground"
                        >{{
                            user.name
                                .split(' ')
                                .slice(0, 2)
                                .map((word) => word[0])
                                .join('')
                        }}</span
                    >
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words">
                            {{ user.name }}
                        </h2>
                        <p class="mt-1 text-sm break-all text-muted-foreground">
                            {{ user.email }}
                        </p>
                        <span
                            class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[13px] font-medium"
                            :class="
                                user.is_owner
                                    ? 'bg-brand-blue-soft text-blue-soft-foreground'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ user.type }}</span
                        >
                    </div>
                </div>
                <p class="mt-4 text-sm text-muted-foreground">
                    Último ingreso: {{ formatDateTime(user.last_login_at) }}
                </p>
                <div
                    v-if="!user.is_owner"
                    class="mt-4 flex items-center justify-between gap-2 border-t pt-3"
                >
                    <Button
                        variant="outline"
                        class="min-h-11"
                        :disabled="busy !== null"
                        @click="assignPassword(user)"
                        ><KeyRound />Asignar contraseña</Button
                    ><Button
                        variant="ghost"
                        size="icon"
                        class="size-11 text-destructive-soft-foreground"
                        :aria-label="`Eliminar usuario ${user.name}`"
                        :disabled="busy !== null"
                        @click="remove(user)"
                        ><Trash2
                    /></Button>
                </div>
            </article>
        </div>
        <div class="hidden rounded-2xl border bg-card shadow-card lg:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b bg-muted">
                    <tr>
                        <th class="w-[21%] p-4 font-semibold">Nombre</th>
                        <th class="w-[24%] p-4 font-semibold">
                            Correo electrónico
                        </th>
                        <th class="w-[13%] p-4 font-semibold">Tipo</th>
                        <th class="w-[18%] p-4 font-semibold">
                            Último ingreso
                        </th>
                        <th class="w-[24%] p-4 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="p-4 font-medium break-words">
                            {{ user.name }}
                        </td>
                        <td class="p-4 break-all">{{ user.email }}</td>
                        <td class="p-4">{{ user.type }}</td>
                        <td class="p-4">
                            {{ formatDateTime(user.last_login_at) }}
                        </td>
                        <td class="p-4">
                            <div
                                v-if="!user.is_owner"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <Button
                                    variant="outline"
                                    class="min-h-11"
                                    :disabled="busy !== null"
                                    @click="assignPassword(user)"
                                    ><KeyRound />Asignar contraseña</Button
                                ><Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-11 text-destructive-soft-foreground hover:text-destructive-soft-foreground"
                                    :aria-label="`Eliminar usuario ${user.name}`"
                                    :disabled="busy !== null"
                                    @click="remove(user)"
                                    ><Trash2
                                /></Button>
                            </div>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ResourcePagination :links="users.links" :meta="users.meta" />
        <UserFormDialog
            v-model:open="createOpen"
            :password-rules="passwordRules"
        />
        <SetUserPasswordDialog
            v-model:open="passwordOpen"
            :user="selectedUser"
            :password-rules="passwordRules"
        />
    </div>
</template>

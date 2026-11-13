<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { KeyRound, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ResourcePagination from '@/components/ResourcePagination.vue';
import UserFormDialog from '@/components/UserFormDialog.vue';
import SetUserPasswordDialog from '@/components/SetUserPasswordDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { formatDateTime } from '@/lib/format';
import { submitWithPassword } from '@/lib/submitWithPassword';
import { destroy, permission } from '@/routes/users';
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
async function changePermission(
    user: ManagedUser,
    enabled: boolean,
): Promise<void> {
    if (
        busy.value !== null ||
        user.is_owner ||
        enabled === user.can_manage_cards
    )
        return;
    busy.value = user.id;
    try {
        await submitWithPassword(
            'Confirma tu contraseña para cambiar el permiso',
            (password, callbacks) => {
                router.put(
                    permission.url(user.id),
                    { can_manage_cards: enabled, current_password: password },
                    { ...callbacks, preserveScroll: true },
                );
            },
            {
                description: `${enabled ? 'Activar' : 'Desactivar'} el permiso de gestión de ${user.name}.`,
            },
        );
    } finally {
        busy.value = null;
    }
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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-primary">Usuarios</h1>
            <Button class="min-h-11" @click="createOpen = true"
                ><Plus />Nuevo usuario</Button
            >
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="border-b bg-slate-50">
                    <tr>
                        <th class="p-4 font-semibold">Nombre</th>
                        <th class="p-4 font-semibold">Correo electrónico</th>
                        <th class="p-4 font-semibold">Tipo</th>
                        <th class="p-4 font-semibold">Gestión de clientes</th>
                        <th class="p-4 font-semibold">Último ingreso</th>
                        <th class="p-4 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="p-4 font-medium">{{ user.name }}</td>
                        <td class="p-4">{{ user.email }}</td>
                        <td class="p-4">{{ user.type }}</td>
                        <td class="p-4">
                            <span v-if="user.is_owner">Siempre permitido</span>
                            <div v-else class="flex items-center gap-2">
                                <Switch
                                    :model-value="user.can_manage_cards"
                                    :aria-label="`Permiso de gestión de ${user.name}`"
                                    :disabled="busy !== null"
                                    @update:model-value="
                                        changePermission(user, $event)
                                    "
                                /><Spinner v-if="busy === user.id" />
                            </div>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ formatDateTime(user.last_login_at) }}
                        </td>
                        <td class="p-4">
                            <div
                                v-if="!user.is_owner"
                                class="flex items-center gap-2"
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
                                    class="size-11 text-destructive hover:text-destructive"
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

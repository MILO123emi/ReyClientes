<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { logout } from '@/routes';
import { index } from '@/routes/clients';
import { index as users } from '@/routes/users';
import { index as audit } from '@/routes/audit';
import { change } from '@/routes/password';

const page = usePage();
const menuOpen = ref(false);
const signingOut = ref(false);
const items = computed(() => [
    { title: 'Clientes', href: index().url },
    ...(page.props.auth.user.can.manageUsers
        ? [{ title: 'Usuarios', href: users().url }]
        : []),
    ...(page.props.auth.user.can.viewAudit
        ? [{ title: 'Auditoría', href: audit().url }]
        : []),
    { title: 'Cambiar contraseña', href: change().url },
]);

function signOut(): void {
    signingOut.value = true;
    menuOpen.value = false;
    router.post(
        logout().url,
        {},
        {
            onFinish: () => {
                signingOut.value = false;
            },
        },
    );
}
</script>

<template>
    <header class="border-b-4 border-brand-yellow bg-white">
        <div
            class="mx-auto flex min-h-20 max-w-7xl items-center gap-3 px-4 py-3 sm:px-6"
        >
            <Link
                :href="index()"
                class="flex min-w-0 items-center gap-3"
                aria-label="Super El Rey, clientes"
            >
                <AppLogo />
                <span
                    class="max-w-40 text-sm leading-tight font-semibold text-primary sm:max-w-none sm:text-base"
                    >Autorización de tarjeta</span
                >
            </Link>
            <nav
                aria-label="Navegación principal"
                class="ml-auto hidden items-center gap-1 lg:flex"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-accent"
                    :class="
                        page.url.split('?')[0] === item.href
                            ? 'bg-accent text-accent-foreground'
                            : ''
                    "
                >
                    {{ item.title }}
                </Link>
                <Button
                    variant="ghost"
                    class="min-h-11"
                    :disabled="signingOut"
                    @click="signOut"
                    ><LogOut />Cerrar sesión</Button
                >
            </nav>
            <Sheet v-model:open="menuOpen">
                <SheetTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="ml-auto size-11 shrink-0 lg:hidden"
                        aria-label="Abrir menú"
                        ><Menu
                    /></Button>
                </SheetTrigger>
                <SheetContent side="left" class="w-80 max-w-[90vw] p-5">
                    <SheetHeader class="px-0 pt-4 text-left">
                        <AppLogo />
                        <SheetTitle>Menú de navegación</SheetTitle>
                    </SheetHeader>
                    <nav aria-label="Navegación móvil" class="grid gap-2">
                        <Link
                            v-for="item in items"
                            :key="item.title"
                            :href="item.href"
                            class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-accent"
                            @click="menuOpen = false"
                            >{{ item.title }}</Link
                        >
                        <Button
                            variant="ghost"
                            class="min-h-11 justify-start px-3"
                            :disabled="signingOut"
                            @click="signOut"
                            ><LogOut />Cerrar sesión</Button
                        >
                    </nav>
                </SheetContent>
            </Sheet>
        </div>
    </header>
</template>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { KeyRound, LogOut, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { index } from '@/routes/clients';
import { index as users } from '@/routes/users';
import { index as audit } from '@/routes/audit';
import { change } from '@/routes/password';
import { email as accountEmail } from '@/routes/account';
const page = usePage();
const signingOut = ref(false);
const initials = computed(() =>
    page.props.auth.user.name
        .split(' ')
        .slice(0, 2)
        .map((word) => word[0])
        .join(''),
);
const items = [
    { title: 'Clientes', href: index().url },
    { title: 'Usuarios', href: users().url },
    { title: 'Registro de cambios', href: audit().url },
];
function signOut(): void {
    signingOut.value = true;
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
    <header
        class="sticky top-0 z-30 bg-brand-navy pt-[env(safe-area-inset-top)] text-white"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6"
        >
            <Link
                :href="index()"
                class="flex min-w-0 items-center gap-2"
                aria-label="ReyPass, clientes"
            >
                <img
                    src="/logo-header.png"
                    alt="Super El Rey"
                    class="h-10 w-auto"
                />
                <span class="leading-tight"
                    ><span class="block text-lg font-bold">ReyPass</span
                    ><span
                        class="block text-[13px] text-white/70 max-[359px]:hidden"
                        >Super El Rey</span
                    ></span
                >
            </Link>
            <nav
                v-if="page.props.auth.user.can.manageUsers"
                aria-label="Navegación principal"
                class="ml-auto hidden h-full items-center gap-1 lg:flex"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="
                        page.url.split('?')[0] === item.href
                            ? 'page'
                            : undefined
                    "
                    class="flex h-full items-center border-b-[3px] px-3 text-sm font-medium"
                    :class="
                        page.url.split('?')[0] === item.href
                            ? 'border-brand-yellow text-white'
                            : 'border-transparent text-white/80 hover:text-white'
                    "
                    >{{ item.title }}</Link
                >
            </nav>
            <div class="ml-auto flex items-center gap-1 lg:ml-2">
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child
                        ><button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-full"
                            aria-label="Abrir menú de cuenta"
                        >
                            <span
                                class="flex size-10 items-center justify-center rounded-full bg-brand-yellow text-sm font-bold text-[#0B1F4B]"
                                >{{ initials }}</span
                            >
                        </button></DropdownMenuTrigger
                    >
                    <DropdownMenuContent align="end" class="w-64">
                        <DropdownMenuLabel class="break-words"
                            >{{ page.props.auth.user.name
                            }}<span
                                class="mt-1 block text-sm font-normal text-muted-foreground"
                                >{{
                                    page.props.auth.user.can.manageUsers
                                        ? 'Administradora'
                                        : 'Empleado'
                                }}</span
                            ></DropdownMenuLabel
                        >
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child class="min-h-11"
                            ><Link :href="change()"
                                ><KeyRound />Cambiar contraseña</Link
                            ></DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="page.props.auth.user.can.changeEmail"
                            as-child
                            class="min-h-11"
                            ><Link :href="accountEmail()"
                                ><Mail />Cambiar correo</Link
                            ></DropdownMenuItem
                        >
                        <DropdownMenuItem
                            class="min-h-11"
                            :disabled="signingOut"
                            @select="signOut"
                            ><LogOut />Cerrar sesión</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <div class="brand-stripe" />
    </header>
</template>

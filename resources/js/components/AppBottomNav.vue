<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Users, ContactRound, History } from '@lucide/vue';
import { index } from '@/routes/clients';
import { index as users } from '@/routes/users';
import { index as audit } from '@/routes/audit';
const page = usePage();
const items = [
    { title: 'Clientes', href: index().url, icon: ContactRound },
    { title: 'Usuarios', href: users().url, icon: Users },
    { title: 'Registro', href: audit().url, icon: History },
];
</script>
<template>
    <nav
        v-if="page.props.auth.user.can.manageUsers"
        aria-label="Navegación inferior"
        class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-3 border-t bg-card pb-[env(safe-area-inset-bottom)] lg:hidden"
    >
        <Link
            v-for="item in items"
            :key="item.title"
            :href="item.href"
            :aria-current="
                page.url.split('?')[0] === item.href ? 'page' : undefined
            "
            class="flex h-16 flex-col items-center justify-center gap-0.5 text-[13px] font-semibold"
            :class="
                page.url.split('?')[0] === item.href
                    ? 'text-link'
                    : 'text-muted-foreground'
            "
        >
            <span
                class="flex h-7 w-14 items-center justify-center rounded-full"
                :class="
                    page.url.split('?')[0] === item.href
                        ? 'bg-brand-blue-soft'
                        : ''
                "
                ><component :is="item.icon" class="size-5" /></span
            >{{ item.title }}
        </Link>
    </nav>
</template>

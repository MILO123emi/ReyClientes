<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';
defineProps<{ title?: string; description?: string }>();
</script>
<template>
    <div class="relative min-h-svh bg-background lg:grid lg:grid-cols-2">
        <div
            class="absolute top-[max(12px,env(safe-area-inset-top))] right-4 z-10 rounded-xl text-white lg:text-foreground"
        >
            <ThemeToggle />
        </div>
        <div
            class="relative flex min-h-[34svh] flex-col items-center justify-center overflow-hidden bg-brand-navy px-6 pt-6 pb-12 text-center text-white lg:min-h-svh lg:py-12"
        >
            <div
                class="pointer-events-none absolute -top-20 -left-32 hidden size-96 rounded-full bg-brand-blue/15 blur-3xl lg:block"
            />
            <div
                class="pointer-events-none absolute -right-32 -bottom-24 hidden size-96 rounded-full bg-brand-yellow/10 blur-3xl lg:block"
            />
            <Link
                :href="home()"
                class="relative flex flex-col items-center gap-2"
                ><img
                    src="/logo.png"
                    alt="Super El Rey"
                    class="h-auto w-full max-w-[220px]"
                /><span class="text-3xl font-bold">ReyPass</span></Link
            >
            <p class="relative mt-2 text-sm text-white/80">
                El rey de los precios bajos
            </p>
            <div class="absolute inset-x-0 bottom-6 brand-stripe lg:bottom-0" />
        </div>
        <div
            class="relative -mt-6 rounded-t-3xl bg-card px-6 py-8 lg:mt-0 lg:flex lg:items-center lg:justify-center lg:rounded-none lg:bg-background lg:px-10"
        >
            <div class="mx-auto grid w-full max-w-sm gap-7">
                <div class="space-y-2">
                    <h1 class="text-2xl leading-8 font-bold">{{ title }}</h1>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
        <Toaster />
    </div>
</template>

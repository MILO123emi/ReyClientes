<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index } from '@/routes/clients';
import type { ClientFilters, SearchCounts, SearchRole } from '@/types/cards';

const props = defineProps<{ filters: ClientFilters; counts: SearchCounts }>();
const emit = defineEmits<{ beforeNavigate: []; busy: [value: boolean] }>();
const options = computed(() => [
    {
        value: 'todos' as const,
        label: 'Todos',
        count: props.counts.owners + props.counts.authorized,
    },
    { value: 'duenos' as const, label: 'Dueños', count: props.counts.owners },
    {
        value: 'autorizados' as const,
        label: 'Autorizados',
        count: props.counts.authorized,
    },
]);

function select(role: SearchRole): void {
    emit('beforeNavigate');
    const parameters: Record<string, string | number> = props.filters.dni
        ? { dni: 1 }
        : props.filters.q
          ? { q: props.filters.q }
          : {};
    if (role !== 'todos') parameters.rol = role;
    router.get(index().url, parameters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => emit('busy', true),
        onFinish: () => emit('busy', false),
    });
}

function move(event: KeyboardEvent, current: number): void {
    const offset =
        event.key === 'ArrowRight' || event.key === 'ArrowDown'
            ? 1
            : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
              ? -1
              : 0;
    if (!offset && event.key !== 'Home' && event.key !== 'End') return;
    event.preventDefault();
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? 2
              : (current + offset + 3) % 3;
    const group = (event.currentTarget as HTMLElement).parentElement;
    (group?.children[next] as HTMLButtonElement)?.focus();
    select(options.value[next].value);
}
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Filtrar por rol"
        class="mt-3 grid grid-cols-3 gap-1.5"
    >
        <button
            v-for="(option, position) in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="filters.role === option.value"
            :tabindex="filters.role === option.value ? 0 : -1"
            class="flex min-h-11 min-w-0 flex-wrap items-center justify-center gap-x-1.5 rounded-xl border px-1 py-2 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:px-3 sm:text-sm"
            :class="
                filters.role === option.value
                    ? 'border-primary bg-primary text-white'
                    : 'bg-card text-foreground'
            "
            @click="select(option.value)"
            @keydown="move($event, position)"
        >
            <span>{{ option.label }}</span
            ><span class="tabular-nums">{{ option.count }}</span>
        </button>
    </div>
</template>

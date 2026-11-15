<script setup lang="ts">
import { Maximize2, Minus, Plus, RotateCcw, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PhotoUrls } from '@/types/cards';

defineProps<{
    photo: PhotoUrls;
    name: string;
    compact?: boolean;
    large?: boolean;
}>();
const open = ref(false);
const zoom = ref(1);
const pointers = new Map<number, { x: number; y: number }>();
let pinchDistance = 0;
let pinchZoom = 1;
let lastTap = 0;

function changeZoom(value: number): void {
    zoom.value = Math.min(4, Math.max(1, value));
}
function reset(): void {
    zoom.value = 1;
    pointers.clear();
    pinchDistance = 0;
}
watch(open, reset);
function distance(): number {
    const [a, b] = [...pointers.values()];
    return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0;
}
function pointerDown(event: PointerEvent): void {
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (pointers.size === 2) {
        pinchDistance = distance();
        pinchZoom = zoom.value;
    }
}
function pointerMove(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (pointers.size === 2 && pinchDistance > 0)
        changeZoom((pinchZoom * distance()) / pinchDistance);
}
function pointerUp(event: PointerEvent): void {
    const wasSingleTouch = pointers.size === 1 && event.pointerType === 'touch';
    pointers.delete(event.pointerId);
    if (wasSingleTouch) {
        const now = Date.now();
        if (now - lastTap < 300) reset();
        lastTap = now;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <button
            type="button"
            class="relative shrink-0 overflow-hidden rounded-xl border bg-muted focus-visible:ring-2 focus-visible:ring-primary"
            :class="large ? 'block w-full' : ''"
            :aria-label="`Ver foto de la cédula de ${name}`"
            @click="open = true"
        >
            <img
                :src="large ? photo.full : photo.thumb"
                :alt="`Foto de la cédula de ${name}`"
                loading="lazy"
                :class="
                    large
                        ? 'aspect-[3/2] w-full object-cover'
                        : compact
                          ? 'h-11 w-16 object-cover'
                          : 'aspect-[3/2] w-28 object-cover'
                "
            />
            <Maximize2
                class="absolute right-1 bottom-1 size-4 rounded bg-black/60 p-0.5 text-white"
            />
        </button>
        <DialogContent
            :show-close-button="false"
            class="inset-0 flex h-dvh w-screen max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none border-0 bg-black/90 p-0 text-white sm:max-w-none"
        >
            <div class="flex shrink-0 items-center justify-between gap-3 p-3">
                <DialogTitle class="truncate text-sm"
                    >Cédula de {{ name }}</DialogTitle
                >
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-11"
                    aria-label="Cerrar visor de foto"
                    @click="open = false"
                    ><X />Cerrar</Button
                >
            </div>
            <DialogDescription class="sr-only"
                >Usa la rueda o dos dedos para ampliar. Doble clic o doble toque
                para restablecer el zoom.</DialogDescription
            >
            <div
                class="relative flex min-h-0 flex-1 touch-none items-center justify-center overflow-auto"
                @wheel.prevent="
                    changeZoom(zoom + ($event.deltaY < 0 ? 0.2 : -0.2))
                "
                @dblclick="reset"
                @pointerdown="pointerDown"
                @pointermove="pointerMove"
                @pointerup="pointerUp"
                @pointercancel="pointers.delete($event.pointerId)"
            >
                <img
                    :src="photo.full"
                    :alt="`Foto de la cédula de ${name}`"
                    draggable="false"
                    class="max-h-full max-w-full origin-center object-contain select-none"
                    :style="{ transform: `scale(${zoom})` }"
                />
            </div>
            <div class="flex shrink-0 items-center justify-center gap-3 p-3">
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    class="size-11"
                    aria-label="Reducir zoom"
                    :disabled="zoom <= 1"
                    @click="changeZoom(zoom - 0.25)"
                    ><Minus
                /></Button>
                <span class="min-w-14 text-center text-sm" aria-live="polite"
                    >{{ Math.round(zoom * 100) }} %</span
                >
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    class="size-11"
                    aria-label="Ampliar zoom"
                    :disabled="zoom >= 4"
                    @click="changeZoom(zoom + 0.25)"
                    ><Plus
                /></Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    class="size-11"
                    aria-label="Restablecer zoom"
                    @click="reset"
                    ><RotateCcw
                /></Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

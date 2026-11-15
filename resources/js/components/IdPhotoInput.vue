<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

const model = defineModel<File | null>({ required: true });
const props = defineProps<{ currentPhoto?: string; disabled?: boolean }>();
const emit = defineEmits<{ error: [message: string] }>();
const gallery = ref<HTMLInputElement | null>(null);
const supported = /\.(jpe?g|png|webp|gif|bmp|avif)$/i;
const previewUrl = ref('');
const error = ref('');
const preview = computed(() => previewUrl.value || props.currentPhoto);

watch(model, (file) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file ? URL.createObjectURL(file) : '';
});
onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function choose(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    const problem = /\.(heic|heif)$/i.test(file.name)
        ? 'Las fotos HEIC del iPhone no se pueden leer. Envíala o guárdala como JPG.'
        : !supported.test(file.name) ||
            (file.type !== '' && !file.type.startsWith('image/'))
          ? 'Ese archivo no es una foto. Elige una imagen JPG o PNG.'
          : file.size > 8 * 1024 * 1024
            ? 'La foto no puede pesar más de 8 MB.'
            : '';
    if (problem) {
        error.value = problem;
        emit('error', error.value);
        return;
    }
    error.value = '';
    emit('error', '');
    model.value = file;
}
</script>

<template>
    <div class="grid gap-3">
        <div
            class="relative flex aspect-[3/2] flex-wrap content-end justify-center gap-2 overflow-hidden rounded-xl border-2 border-dashed bg-muted p-4"
        >
            <img
                v-if="preview"
                :src="preview"
                alt="Vista previa de la foto de la cédula"
                class="absolute inset-0 size-full object-cover"
            />
            <div
                v-else
                class="flex w-full flex-col items-center justify-center gap-3 self-center py-6 text-muted-foreground"
            >
                <ImagePlus class="size-9" /><span class="text-sm"
                    >Elige la foto de la cédula</span
                >
            </div>
            <Button
                v-if="model"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute top-2 right-2 z-10 size-11"
                aria-label="Quitar la foto elegida"
                :disabled="disabled"
                @click="model = null"
                ><X
            /></Button>
            <Button
                type="button"
                variant="outline"
                class="relative min-h-11 bg-card"
                :disabled="disabled"
                @click="gallery?.click()"
                ><ImagePlus />{{
                    preview ? 'Cambiar foto' : 'Elegir foto'
                }}</Button
            >
        </div>
        <input
            ref="gallery"
            type="file"
            accept="image/*"
            class="hidden"
            aria-label="Elegir foto de la cédula"
            :disabled="disabled"
            @change="choose"
        />
        <p class="text-[13px] text-muted-foreground">
            Sirve cualquier foto: JPG, PNG, WebP, GIF, BMP o AVIF. Máximo 8 MB.
        </p>
        <InputError :message="error" />
    </div>
</template>

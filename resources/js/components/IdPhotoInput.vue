<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

const model = defineModel<File | null>({ required: true });
const props = defineProps<{ currentPhoto?: string; disabled?: boolean }>();
const emit = defineEmits<{ error: [message: string] }>();
const camera = ref<HTMLInputElement | null>(null);
const gallery = ref<HTMLInputElement | null>(null);
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
    if (file.size > 8 * 1024 * 1024) {
        error.value = 'La foto no puede pesar más de 8 MB.';
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
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="disabled"
                @click="camera?.click()"
                ><Camera />Tomar foto</Button
            >
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="disabled"
                @click="gallery?.click()"
                ><ImagePlus />Elegir de galería</Button
            >
        </div>
        <input
            ref="camera"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            aria-label="Tomar foto de la cédula"
            :disabled="disabled"
            @change="choose"
        />
        <input
            ref="gallery"
            type="file"
            accept="image/*"
            class="hidden"
            aria-label="Elegir foto de la cédula"
            :disabled="disabled"
            @change="choose"
        />
        <div v-if="preview" class="relative w-fit">
            <img
                :src="preview"
                alt="Vista previa de la foto de la cédula"
                class="max-h-44 rounded-md border object-contain"
            />
            <Button
                v-if="model"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute top-1 right-1 size-11"
                aria-label="Quitar la foto elegida"
                :disabled="disabled"
                @click="model = null"
                ><X
            /></Button>
        </div>
        <p class="text-xs text-muted-foreground">
            JPG, PNG o WebP. Máximo 8 MB.
        </p>
        <InputError :message="error" />
    </div>
</template>

<script setup lang="ts">
import { LIMITS, type Cover } from '@focus/types';
import { coverOptions } from './covers';

export type CoverChoice = { type: 'gallery'; key: Cover } | { type: 'upload'; url: string; file?: File } | null;

defineProps<{ label: string; error?: string }>();
const model = defineModel<CoverChoice>({ required: true });
const emit = defineEmits<{ invalid: [message: string] }>();

const upload = ref<{ url: string; file?: File } | null>(model.value?.type === 'upload' ? model.value : null);
const options = computed(() => [
    ...(upload.value ? [{ value: 'upload' as const, label: 'Your photo', url: upload.value.url }] : []),
    ...coverOptions.map((option) => ({ value: option.key, label: option.label, url: option.url })),
]);
const selected = computed(() => (model.value?.type === 'gallery' ? model.value.key : model.value ? 'upload' : ''));

watch(model, (value) => {
    if (value?.type === 'upload') upload.value = value;
});

function choose(value: Cover | 'upload' | ''): void {
    if (value === 'upload') {
        if (upload.value) model.value = { type: 'upload', ...upload.value };
    } else if (value) model.value = { type: 'gallery', key: value };
}

function onFile(file: File): void {
    if (file.size > LIMITS.coverKb * 1024) {
        emit('invalid', `Choose an image under ${LIMITS.coverKb / 1024} MB.`);
        return;
    }
    if (upload.value?.file) URL.revokeObjectURL(upload.value.url);
    upload.value = { url: URL.createObjectURL(file), file };
    model.value = { type: 'upload', ...upload.value };
}

onBeforeUnmount(() => {
    if (upload.value?.file) URL.revokeObjectURL(upload.value.url);
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <ImageChoiceGroup :model-value="selected" :label="label" :options="options" @update:model-value="choose">
            <FilePicker
                v-slot="{ open }"
                accept="image/jpeg,image/png,image/webp"
                label="Upload a cover photo"
                @pick="onFile"
            >
                <Pressable
                    type="button"
                    class="flex aspect-video flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed border-border-control text-caption font-semibold text-fg-muted transition-colors duration-(--duration-fast) hover:border-border-strong hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring"
                    @click="open"
                >
                    <Icon name="upload" />{{ upload ? 'Replace' : 'Upload' }}
                </Pressable>
            </FilePicker>
        </ImageChoiceGroup>
        <Text v-if="error" role="caption" tone="danger">{{ error }}</Text>
        <Text v-else role="caption" tone="muted">
            Photos from <a href="https://www.pexels.com" target="_blank" rel="noopener" class="underline">Pexels</a>,
            or your own JPG, PNG or WebP.
        </Text>
    </div>
</template>

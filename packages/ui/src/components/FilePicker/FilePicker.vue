<script setup lang="ts">
import { LvFileUploadInput, LvFileUploadRoot } from '@lovince/ui-vue';
import { nextTick, ref } from 'vue';

/**
 * Picks one file through Lovince's file upload (also accepts a drop on the slot content).
 * The slot gets `open` to show the system picker; `pick` fires with the chosen file.
 */
defineProps<{ accept?: string; label: string; disabled?: boolean }>();
const emit = defineEmits<{ pick: [file: File] }>();

const files = ref<readonly File[]>([]);
const input = ref<{ $el: HTMLInputElement } | null>(null);

function onFiles(next: readonly File[]): void {
    files.value = next;
    const file = next.at(-1);
    if (file) emit('pick', file);
    void nextTick(() => (files.value = []));
}

const open = (): void => input.value?.$el.click();
</script>

<template>
    <LvFileUploadRoot
        :model-value="files"
        :accept="accept"
        :multiple="false"
        :disabled="disabled"
        class="contents"
        @update:model-value="onFiles"
    >
        <slot :open="open" />
        <LvFileUploadInput ref="input" :aria-label="label" class="sr-only" tabindex="-1" />
    </LvFileUploadRoot>
</template>

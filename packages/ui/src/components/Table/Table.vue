<script setup lang="ts">
import { LvTableCaption, LvTableContainer, LvTableRoot } from '@lovince/ui-vue';

defineProps<{ caption: string; label?: string; showCaption?: boolean }>();
</script>

<template>
    <LvTableContainer :label="label" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-focus-ring">
        <LvTableRoot class="w-full text-label">
            <LvTableCaption :class="showCaption ? 'pb-2 text-left text-caption text-fg-muted' : 'sr-only'">
                {{ caption }}
            </LvTableCaption>
            <slot />
        </LvTableRoot>
    </LvTableContainer>
</template>

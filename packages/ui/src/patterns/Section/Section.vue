<script setup lang="ts">
import {
    LvSectionContent,
    LvSectionDescription,
    LvSectionEyebrow,
    LvSectionHeader,
    LvSectionRoot,
    LvSectionTitle,
} from '@lovince/ui-vue';

withDefaults(defineProps<{ title: string; eyebrow?: string; description?: string; level?: 2 | 3 }>(), { level: 2 });
</script>

<template>
    <LvSectionRoot spacing="none" class="flex flex-col gap-4">
        <LvSectionHeader class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <LvSectionEyebrow
                    v-if="eyebrow"
                    class="text-caption font-semibold tracking-wide text-fg-muted uppercase"
                >
                    {{ eyebrow }}
                </LvSectionEyebrow>
                <LvSectionTitle :level="level" class="font-display text-title font-semibold tracking-tight">
                    <slot name="title">{{ title }}</slot>
                </LvSectionTitle>
                <LvSectionDescription v-if="description" class="text-label text-fg-muted">
                    {{ description }}
                </LvSectionDescription>
            </div>
            <slot name="actions" />
        </LvSectionHeader>
        <LvSectionContent class="flex flex-col gap-4">
            <slot />
        </LvSectionContent>
    </LvSectionRoot>
</template>

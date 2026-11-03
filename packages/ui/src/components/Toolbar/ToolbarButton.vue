<script setup lang="ts">
import { LvToolbarButton } from '@lovince/ui-vue';
import { ref } from 'vue';
import type { IconName } from '../../icons/registry';
import IconButton from '../IconButton/IconButton.vue';

/** An icon-only toolbar item: an IconButton that joins the toolbar's arrow-key focus. */
defineOptions({ inheritAttrs: false });
defineProps<{ icon: IconName; label: string; shortcut?: string; disabled?: boolean }>();

const button = ref<{ focus: () => void } | null>(null);

defineExpose({ focus: () => button.value?.focus() });
</script>

<template>
    <LvToolbarButton as-child :disabled="disabled">
        <IconButton
            ref="button"
            v-bind="$attrs"
            :icon="icon"
            :label="label"
            :shortcut="shortcut"
            variant="ghost"
            size="sm"
            :disabled="disabled"
        />
    </LvToolbarButton>
</template>

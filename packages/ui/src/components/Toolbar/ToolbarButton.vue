<script setup lang="ts">
import { LvToolbarButton } from '@lovince/ui-vue';
import { ref } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import { buttonVariants, controlSquares, interactive } from '../Button/variants';
import Tooltip from '../Tooltip/Tooltip.vue';

/** An icon-only toolbar item. `label` is its accessible name and tooltip; `shortcut` shows beside it. */
defineOptions({ inheritAttrs: false });
defineProps<{ icon: IconName; label: string; shortcut?: string; disabled?: boolean }>();

const button = ref<{ $el: HTMLElement } | null>(null);

defineExpose({ focus: () => button.value?.$el.focus() });
</script>

<template>
    <Tooltip :label="label" :shortcut="shortcut">
        <LvToolbarButton
            ref="button"
            v-bind="$attrs"
            :disabled="disabled"
            :aria-label="label"
            :aria-keyshortcuts="shortcut?.replace(/Ctrl/g, 'Control')"
            :class="[interactive, controlSquares.sm, buttonVariants.ghost]"
        >
            <Icon :name="icon" size="sm" motion="pop" />
        </LvToolbarButton>
    </Tooltip>
</template>

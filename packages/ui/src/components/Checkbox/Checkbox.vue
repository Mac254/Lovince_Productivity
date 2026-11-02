<script setup lang="ts">
import { LvCheckboxIndicator, LvCheckboxRoot } from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';

withDefaults(defineProps<{ id: string; label: string; surface?: 'default' | 'chrome' }>(), { surface: 'default' });
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label
        :for="id"
        :class="[
            'inline-flex min-h-(--control-sm) cursor-pointer items-center gap-3 text-label font-semibold',
            surface === 'chrome' ? 'text-chrome-fg' : 'text-fg-muted',
        ]"
    >
        <LvCheckboxRoot
            :id="id"
            :model-value="model"
            class="inline-flex size-(--icon-md) shrink-0 items-center justify-center rounded-sm border border-border-control bg-surface text-accent-ink transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:outline-2 focus-visible:outline-focus-ring state-checked:border-accent-solid state-checked:bg-accent-solid"
            @update:model-value="model = $event === true"
        >
            <LvCheckboxIndicator class="inline-flex">
                <Icon name="check" />
            </LvCheckboxIndicator>
        </LvCheckboxRoot>
        {{ label }}
    </label>
</template>

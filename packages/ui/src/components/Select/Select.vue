<script setup lang="ts" generic="T extends string | number">
import {
    LvSelectContent,
    LvSelectItem,
    LvSelectItemIndicator,
    LvSelectItemText,
    LvSelectPortal,
    LvSelectRoot,
    LvSelectTrigger,
    LvSelectValue,
} from '@lovince/ui-vue';
import { computed } from 'vue';
import Icon from '../../primitives/Icon/Icon.vue';
import Field from '../Field/Field.vue';
import { controlClasses, type ControlSurface } from '../Field/controlClasses';

export interface SelectOption<V> {
    value: V | '';
    label: string;
}

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    id: string;
    label: string;
    options: SelectOption<T>[];
    error?: string;
    hint?: string;
    optional?: boolean;
    surface?: ControlSurface;
}>();
const model = defineModel<T | '' | null>({ default: '' });

/**
 * Lovince select values are non-empty strings, while options here may be numbers or the empty
 * "any" choice, so items are keyed by position and mapped back to the option's own value.
 */
const selected = computed(() => {
    const index = props.options.findIndex((option) => option.value === model.value);
    return index === -1 ? '' : String(index);
});

function choose(key: string | string[]): void {
    const option = typeof key === 'string' ? props.options[Number(key)] : undefined;
    if (option) model.value = option.value;
}
</script>

<template>
    <Field :id="id" v-slot="{ describedBy, invalid }" :label="label" :error="error" :hint="hint" :optional="optional" :surface="surface">
        <LvSelectRoot :model-value="selected" @update:model-value="choose">
            <LvSelectTrigger
                :id="id"
                v-bind="$attrs"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                :class="[
                    controlClasses(invalid, surface),
                    'group flex h-(--control-md) items-center justify-between gap-2 text-left focus-visible:border-highlight',
                ]"
            >
                <LvSelectValue class="truncate data-placeholder:text-fg-subtle" />
                <Icon
                    name="chevron-down"
                    class="text-fg-muted transition-transform duration-(--duration-fast) group-state-open:rotate-180"
                />
            </LvSelectTrigger>
            <LvSelectPortal>
                <LvSelectContent
                    :side-offset="8"
                    class="select-menu z-(--z-popover) max-h-(--lovince-floating-available-height) w-(--lovince-anchor-width) min-w-48 overflow-y-auto rounded-md border border-border bg-surface-raised p-1 text-fg shadow-floating focus-visible:outline-none"
                >
                    <LvSelectItem
                        v-for="(option, index) in options"
                        :key="String(option.value)"
                        :value="String(index)"
                        :text-value="option.label"
                        class="flex min-h-(--control-sm) cursor-pointer items-center justify-between gap-3 rounded-sm px-3 text-label font-medium outline-none select-none data-disabled:opacity-50 data-highlighted:bg-border"
                    >
                        <LvSelectItemText>{{ option.label }}</LvSelectItemText>
                        <LvSelectItemIndicator class="text-highlight-fg">
                            <Icon name="check" />
                        </LvSelectItemIndicator>
                    </LvSelectItem>
                </LvSelectContent>
            </LvSelectPortal>
        </LvSelectRoot>
    </Field>
</template>

<style scoped>
.select-menu[data-state='open'] {
    animation: select-in var(--duration-fast) var(--ease-out);
}

.select-menu[data-state='closed'] {
    animation: select-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes select-in {
    from {
        opacity: 0;
        scale: 0.98;
    }
}

@keyframes select-out {
    to {
        opacity: 0;
    }
}
</style>

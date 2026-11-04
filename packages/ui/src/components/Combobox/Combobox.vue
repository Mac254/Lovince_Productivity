<script setup lang="ts" generic="T extends string | number">
import {
    LvComboboxAnchor,
    LvComboboxContent,
    LvComboboxEmpty,
    LvComboboxInput,
    LvComboboxItem,
    LvComboboxItemIndicator,
    LvComboboxPortal,
    LvComboboxRoot,
    LvComboboxTrigger,
} from '@lovince/ui-vue';
import { computed } from 'vue';
import { useLayer } from '../../composables/useLayer';
import Icon from '../../primitives/Icon/Icon.vue';
import Field from '../Field/Field.vue';
import { controlClasses, type ControlSurface } from '../Field/controlClasses';
import type { SelectOption } from '../Select/Select.vue';
import { useFieldAttrs } from '../../composables/useFieldAttrs';

defineOptions({ inheritAttrs: false });
const { root, control } = useFieldAttrs();
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        options: SelectOption<T>[];
        error?: string;
        hint?: string;
        optional?: boolean;
        surface?: ControlSurface;
        empty?: string;
    }>(),
    { empty: 'No matches' },
);
const model = defineModel<T | '' | null>({ default: '' });
const layer = useLayer();

const selected = computed(() => {
    const index = props.options.findIndex((option) => option.value === model.value);
    return index === -1 ? undefined : String(index);
});

function choose(key: string | string[] | undefined): void {
    const option = typeof key === 'string' ? props.options[Number(key)] : undefined;
    if (option) model.value = option.value;
}
</script>

<template>
    <Field
        v-bind="root()"
        :id="id"
        v-slot="{ describedBy, invalid }"
        :label="label"
        :error="error"
        :hint="hint"
        :optional="optional"
        :surface="surface"
    >
        <LvComboboxRoot :model-value="selected" @update:model-value="choose">
            <LvComboboxAnchor class="group relative flex items-center">
                <LvComboboxInput
                    :id="id"
                    v-bind="control()"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                    :class="[controlClasses(invalid, surface), 'h-(--control-md) truncate pr-12']"
                />
                <LvComboboxTrigger
                    :label="`Show ${label.toLowerCase()} options`"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-fg-muted"
                >
                    <Icon
                        name="chevron-down"
                        class="transition-transform duration-(--duration-fast) group-state-open:rotate-180"
                    />
                </LvComboboxTrigger>
            </LvComboboxAnchor>
            <LvComboboxPortal :to="layer">
                <LvComboboxContent
                    :side-offset="8"
                    class="combobox-menu z-(--z-popover) max-h-(--lovince-floating-available-height) w-(--lovince-anchor-width) min-w-48 overflow-y-auto rounded-md border border-border bg-surface-raised p-1 text-fg shadow-floating focus-visible:outline-none"
                >
                    <LvComboboxItem
                        v-for="(option, index) in options"
                        :key="String(option.value)"
                        :value="String(index)"
                        :text-value="option.label"
                        class="flex min-h-(--control-sm) cursor-pointer items-center justify-between gap-3 rounded-sm px-3 text-label font-medium outline-none select-none data-disabled:opacity-50 data-highlighted:bg-border"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <LvComboboxItemIndicator class="text-highlight-fg">
                            <Icon name="check" />
                        </LvComboboxItemIndicator>
                    </LvComboboxItem>
                    <LvComboboxEmpty class="px-3 py-2 text-label text-fg-muted">{{ empty }}</LvComboboxEmpty>
                </LvComboboxContent>
            </LvComboboxPortal>
        </LvComboboxRoot>
    </Field>
</template>

<style scoped>
.combobox-menu[data-state='open'] {
    animation: combobox-in var(--duration-fast) var(--ease-out);
}

.combobox-menu[data-state='closed'] {
    animation: combobox-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes combobox-in {
    from {
        opacity: 0;
        scale: 0.98;
    }
}

@keyframes combobox-out {
    to {
        opacity: 0;
    }
}
</style>

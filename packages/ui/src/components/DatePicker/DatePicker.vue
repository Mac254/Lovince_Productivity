<script setup lang="ts">
import {
    LvCalendarCell,
    LvCalendarDay,
    LvCalendarGrid,
    LvCalendarHeadCell,
    LvCalendarHeading,
    LvCalendarNext,
    LvCalendarPrev,
    LvDatePickerContent,
    LvDatePickerInput,
    LvDatePickerRoot,
    LvDatePickerTrigger,
} from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import Field from '../Field/Field.vue';
import { controlClasses, type ControlSurface } from '../Field/controlClasses';

defineOptions({ inheritAttrs: false });
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    optional?: boolean;
    surface?: ControlSurface;
    min?: string;
    max?: string;
}>();
const model = defineModel<string | null>({ default: '' });

const pager =
    'inline-flex size-(--control-sm) items-center justify-center rounded-md text-fg-muted transition-colors duration-(--duration-fast) hover:bg-border hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring disabled:opacity-40';
</script>

<template>
    <Field
        :id="id"
        v-slot="{ describedBy, invalid }"
        :label="label"
        :error="error"
        :hint="hint"
        :optional="optional"
        :surface="surface"
    >
        <LvDatePickerRoot
            :model-value="model || undefined"
            :min="min"
            :max="max"
            class="relative"
            @update:model-value="model = $event ?? ''"
        >
            <LvDatePickerInput
                :id="id"
                :label="label"
                v-bind="$attrs"
                :aria-describedby="describedBy"
                :class="[controlClasses(invalid, surface), 'h-(--control-md) pr-12 tabular']"
            />
            <LvDatePickerTrigger
                :label="`Choose ${label.toLowerCase()}`"
                :class="[pager, 'absolute inset-y-0 right-2 my-auto']"
            >
                <Icon name="calendar" />
            </LvDatePickerTrigger>
            <LvDatePickerContent
                :side-offset="8"
                align="end"
                class="date-menu z-(--z-popover) rounded-lg border border-border bg-surface-raised p-3 text-fg shadow-floating focus-visible:outline-none"
            >
                <template #default="{ months, weekdays }">
                    <div v-for="month in months" :key="month.month" class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <LvCalendarPrev :class="pager"><Icon name="chevron-left" /></LvCalendarPrev>
                            <LvCalendarHeading :month="month.month" as="p" class="text-label font-semibold" />
                            <LvCalendarNext :class="pager"><Icon name="chevron-right" /></LvCalendarNext>
                        </div>
                        <LvCalendarGrid :month="month.month" class="border-collapse">
                            <thead>
                                <tr>
                                    <LvCalendarHeadCell
                                        v-for="day in weekdays"
                                        :key="day.long"
                                        :label="day.long"
                                        class="pb-1 text-caption font-semibold text-fg-muted"
                                    >
                                        {{ day.short }}
                                    </LvCalendarHeadCell>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(week, w) in month.weeks" :key="w">
                                    <LvCalendarCell v-for="day in week" :key="day.date" :date="day.date" class="p-0">
                                        <LvCalendarDay
                                            :date="day.date"
                                            :month="month.month"
                                            class="inline-flex size-(--control-sm) items-center justify-center rounded-md text-label font-medium tabular transition-colors duration-(--duration-fast) hover:bg-border focus-visible:outline-2 focus-visible:outline-focus-ring data-outside:text-fg-subtle data-today:text-highlight-fg data-unavailable:pointer-events-none data-unavailable:opacity-40 data-selected:bg-highlight data-selected:text-highlight-ink"
                                        />
                                    </LvCalendarCell>
                                </tr>
                            </tbody>
                        </LvCalendarGrid>
                    </div>
                </template>
            </LvDatePickerContent>
        </LvDatePickerRoot>
    </Field>
</template>

<style scoped>
.date-menu[data-state='open'] {
    animation: date-in var(--duration-fast) var(--ease-out);
}

.date-menu[data-state='closed'] {
    animation: date-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes date-in {
    from {
        opacity: 0;
        scale: 0.98;
    }
}

@keyframes date-out {
    to {
        opacity: 0;
    }
}
</style>

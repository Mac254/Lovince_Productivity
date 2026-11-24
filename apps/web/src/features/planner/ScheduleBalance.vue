<script setup lang="ts">
import type { ScheduleItem } from '@focus/types';
import { formatMinutes } from '@/lib/dates';
import { toneDot } from '@/lib/tones';
import { balanceOf, type BalanceSlice } from './balance';

/** How a routine shares the day between kinds of activity, by task type. */
const props = defineProps<{ items: Pick<ScheduleItem, 'minutes' | 'task_type_id'>[] }>();

const types = useTaskTypesStore();
const settings = useSettingsStore();
onMounted(() => void types.load());

const slices = computed(() => balanceOf(props.items, types.types));
const fill = (slice: BalanceSlice) => (slice.key === 'none' ? 'bg-chart-2' : toneDot[slice.key]);
const summary = computed(() =>
    slices.value.map((slice) => `${slice.label} ${formatMinutes(slice.minutes)} (${slice.percent}%)`).join(', '),
);
</script>

<template>
    <figure v-if="settings.values.schedules.show_balance && slices.length" class="flex flex-col gap-3">
        <figcaption class="text-caption font-semibold text-fg-muted uppercase">Balance</figcaption>
        <div role="img" :aria-label="`Time by type: ${summary}`" class="flex h-2 w-full gap-1">
            <span
                v-for="slice in slices"
                :key="slice.key"
                :title="`${slice.label}: ${formatMinutes(slice.minutes)} · ${slice.percent}%`"
                :class="['h-full min-w-1 rounded-full', fill(slice)]"
                :style="{ flexGrow: slice.minutes }"
            />
        </div>
        <ul class="flex flex-col gap-1" aria-hidden="true">
            <li v-for="slice in slices" :key="slice.key" class="flex items-center gap-2 text-caption">
                <span :class="['size-2 shrink-0 rounded-full', fill(slice)]" />
                <span class="min-w-0 flex-1 truncate text-fg">{{ slice.label }}</span>
                <span class="text-fg-muted tabular">{{ formatMinutes(slice.minutes) }} · {{ slice.percent }}%</span>
            </li>
        </ul>
    </figure>
</template>

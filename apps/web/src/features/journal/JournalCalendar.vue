<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { monthGrid, monthOf, shiftDate, WEEKDAYS, type MonthIso } from '@/lib/calendar';
import { formatDate, todayIso } from '@/lib/dates';

/**
 * A month of days, Monday first. Days with an entry show a dot and, unless `compact`, the first line
 * of the note. Arrow keys move between days (crossing months), Enter or click opens one; future days
 * are disabled.
 */
const props = defineProps<{ month: MonthIso; selected: string; previews: Record<string, string>; compact?: boolean }>();
const emit = defineEmits<{ select: [date: string]; 'update:month': [month: MonthIso] }>();

const today = todayIso();
const weeks = computed(() => monthGrid(props.month));
const focused = ref(props.selected);
const grid = ref<HTMLElement | null>(null);

watch(
    () => props.selected,
    (date) => (focused.value = date),
);
watch(
    () => props.month,
    (month) => {
        if (monthOf(focused.value) !== month)
            focused.value = props.selected.startsWith(month) ? props.selected : `${month}-01`;
    },
);

const firstLine = (text: string) => text.split('\n').find((line) => line.trim()) ?? '';
const label = (date: string) =>
    [
        formatDate(date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
        date === today ? 'today' : '',
        props.previews[date] ? 'has an entry' : 'no entry',
    ]
        .filter(Boolean)
        .join(', ');

async function move(days: number): Promise<void> {
    const next = shiftDate(focused.value, days);
    if (next > today) return;
    focused.value = next;
    if (monthOf(next) !== props.month) emit('update:month', monthOf(next));
    await nextTick();
    grid.value?.querySelector<HTMLButtonElement>(`[data-date="${next}"]`)?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
    if (step) {
        event.preventDefault();
        void move(step);
    }
}
</script>

<template>
    <div
        ref="grid"
        role="grid"
        :aria-label="formatDate(`${month}-01`, { month: 'long', year: 'numeric' })"
        @keydown="onKeydown"
    >
        <div role="row" class="grid grid-cols-7 border-b border-border pb-2">
            <span
                v-for="day in WEEKDAYS"
                :key="day"
                role="columnheader"
                class="text-center text-caption font-semibold tracking-wide text-fg-muted uppercase"
                >{{ day }}</span
            >
        </div>
        <div
            v-for="(week, w) in weeks"
            :key="w"
            role="row"
            :class="['grid grid-cols-7', !compact && 'border-b border-border last:border-b-0']"
        >
            <div
                v-for="cell in week"
                :key="cell.date"
                role="gridcell"
                :class="compact ? 'p-0' : 'border-r border-border p-1 last:border-r-0'"
            >
                <button
                    type="button"
                    :data-date="cell.date"
                    :tabindex="cell.date === focused ? 0 : -1"
                    :disabled="cell.date > today"
                    :aria-label="label(cell.date)"
                    :aria-selected="cell.date === selected"
                    :aria-current="cell.date === today ? 'date' : undefined"
                    :class="[
                        'flex h-full w-full flex-col gap-1 rounded-md transition-colors duration-(--duration-instant) focus-visible:outline-2 focus-visible:outline-focus-ring disabled:cursor-not-allowed',
                        compact ? 'min-h-11 items-center justify-center p-1' : 'min-h-16 p-2 text-left sm:min-h-24',
                        cell.date === selected ? 'bg-surface-raised ring-1 ring-highlight' : 'hover:bg-surface-raised',
                        !cell.inMonth && 'opacity-40',
                        cell.date > today && 'opacity-30',
                    ]"
                    @click="emit('select', cell.date)"
                    @focus="focused = cell.date"
                >
                    <span v-if="compact" class="flex flex-col items-center gap-1">
                        <span
                            :class="[
                                'inline-flex size-6 items-center justify-center rounded-full text-label font-semibold tabular',
                                cell.date === today ? 'bg-highlight text-highlight-ink' : 'text-fg',
                            ]"
                            >{{ cell.day }}</span
                        >
                        <span
                            aria-hidden="true"
                            :class="['size-1 rounded-full', previews[cell.date] ? 'bg-highlight-fg' : 'bg-transparent']"
                        />
                    </span>
                    <span v-else class="flex items-center justify-between gap-1">
                        <span
                            :class="[
                                'inline-flex size-6 items-center justify-center rounded-full text-label font-semibold tabular',
                                cell.date === today ? 'bg-highlight text-highlight-ink' : 'text-fg',
                            ]"
                            >{{ cell.day }}</span
                        >
                        <span
                            v-if="previews[cell.date]"
                            aria-hidden="true"
                            class="size-2 rounded-full bg-highlight-fg sm:hidden"
                        />
                    </span>
                    <span
                        v-if="previews[cell.date] && !compact"
                        aria-hidden="true"
                        class="hidden border-l-2 border-highlight-fg pl-2 text-caption text-fg-muted sm:line-clamp-2"
                        >{{ firstLine(previews[cell.date]!) }}</span
                    >
                </button>
            </div>
        </div>
    </div>
</template>

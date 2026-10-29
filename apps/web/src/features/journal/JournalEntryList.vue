<script setup lang="ts">
import { Icon } from '@focus/ui';
import { computed } from 'vue';
import { formatDate, todayIso } from '@/lib/dates';
import { summarizeEntry } from '@/lib/markdownLite';

/**
 * Entries as a list, newest first: the date, the first thing written, and its sections. `acrossMonths`
 * labels each date with its month instead of its weekday.
 */
const props = defineProps<{ entries: { date: string; body: string }[]; selected: string; acrossMonths?: boolean }>();
const emit = defineEmits<{ select: [date: string] }>();

const today = todayIso();
const rows = computed(() =>
    [...props.entries]
        .sort((a, b) => b.date.localeCompare(a.date))
        .map((entry) => {
            const summary = summarizeEntry(entry.body);
            const meta = summary.sections.length ? summary.sections.join(' · ') : '';
            const items = summary.items ? `${summary.items} ${summary.items === 1 ? 'item' : 'items'}` : '';
            return { ...entry, title: summary.title, meta: [meta, items].filter(Boolean).join(' — ') };
        }),
);
</script>

<template>
    <ul class="-mx-2 flex flex-col gap-1">
        <li v-for="row in rows" :key="row.date">
            <button
                type="button"
                :aria-current="row.date === selected ? 'date' : undefined"
                :aria-label="`${formatDate(row.date, { weekday: 'long', day: 'numeric', month: 'long' })}: ${row.title}`"
                :class="[
                    'group flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors duration-(--duration-instant) focus-visible:outline-2 focus-visible:outline-focus-ring',
                    row.date === selected ? 'bg-surface-raised ring-1 ring-highlight' : 'hover:bg-surface-raised',
                ]"
                @click="emit('select', row.date)"
            >
                <span
                    aria-hidden="true"
                    :class="[
                        'flex size-11 shrink-0 flex-col items-center justify-center rounded-md border',
                        row.date === today ? 'border-highlight bg-highlight text-highlight-ink' : 'border-border',
                    ]"
                >
                    <span class="font-display text-label leading-none font-semibold tabular">{{
                        Number(row.date.slice(8))
                    }}</span>
                    <span class="text-caption leading-none uppercase opacity-70">{{
                        formatDate(row.date, acrossMonths ? { month: 'short' } : { weekday: 'short' })
                    }}</span>
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="truncate text-label font-semibold">{{ row.title || 'Untitled entry' }}</span>
                    <span v-if="row.meta" class="truncate text-caption text-fg-muted">{{ row.meta }}</span>
                </span>
                <Icon
                    name="arrow-right"
                    motion="nudge"
                    class="shrink-0 text-fg-subtle opacity-0 transition-opacity group-hover:opacity-100"
                />
            </button>
        </li>
    </ul>
</template>

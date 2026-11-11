<script setup lang="ts">
import { LIMITS, type Book } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate } from '@/lib/dates';
import { bookStatusLabels, bookStatusTones } from '@/lib/labels';
import { chapterSummary } from './progress';

const props = defineProps<{ book: Book }>();
const emit = defineEmits<{ edit: [book: Book]; log: [book: Book]; delete: [book: Book] }>();

const menu = computed<MenuItem[]>(() => [
    ...(props.book.status === 'finished'
        ? []
        : [{ label: 'Log reading', icon: 'book' as const, onSelect: () => emit('log', props.book) }]),
    { label: 'Edit book', icon: 'edit', onSelect: () => emit('edit', props.book) },
    { label: 'Delete book', icon: 'trash', destructive: true, onSelect: () => emit('delete', props.book) },
]);

const when = computed(() => {
    const { status, finished_on: finished, last_read_on: last, started_on: started } = props.book;
    if (status === 'finished' && finished) return `Finished ${formatDate(finished)}`;
    if (last) return `Last read ${formatDate(last)}`;
    if (started) return `Started ${formatDate(started)}`;
    return status === 'to_find' ? 'Still to find' : 'On the shelf';
});
</script>

<template>
    <Card interactive class="min-h-56 gap-3">
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <Badge dot :tone="bookStatusTones[book.status]">{{ bookStatusLabels[book.status] }}</Badge>
                <Badge v-if="book.rating" tone="highlight" icon="star">{{ book.rating }}/{{ LIMITS.book.rating }}</Badge>
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${book.title}”`" size="sm" />
        </div>

        <div class="flex flex-col gap-1">
            <Heading :level="3" role="title">
                <RouterLink
                    :to="{ name: 'book-detail', params: { id: book.id } }"
                    class="underline-offset-4 hover:underline"
                    >{{ book.title }}</RouterLink
                >
            </Heading>
            <Text v-if="book.author" role="label" tone="muted">{{ book.author }}</Text>
        </div>
        <Text v-if="book.goal" role="label" tone="muted" class="flex items-center gap-2">
            <Icon name="target" />
            <span class="truncate">{{ book.goal.title }}</span>
        </Text>
        <Text role="label" tone="muted" tabular class="flex items-center gap-2">
            <Icon name="calendar-range" />
            {{ when }}
        </Text>

        <div v-if="book.status !== 'to_find'" class="mt-auto flex flex-col gap-2 pt-2">
            <div class="flex items-baseline justify-between gap-2">
                <span v-if="book.progress !== null" class="font-display text-heading font-semibold tabular"
                    >{{ book.progress }}%</span
                >
                <Text as="span" role="label" tone="muted" tabular>{{ chapterSummary(book) }}</Text>
            </div>
            <ProgressBar
                v-if="book.progress !== null"
                :value="book.progress"
                :label="`${book.title} progress`"
                :tone="book.progress === 100 ? 'highlight' : 'accent'"
            />
        </div>
    </Card>
</template>

<script setup lang="ts">
import { LIMITS, type Book } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate, formatMinutes } from '@/lib/dates';
import { bookStatusTones, statusLabelsFor } from '@/lib/labels';
import { chapterSummary } from './progress';

const props = defineProps<{ book: Book }>();
const emit = defineEmits<{ edit: [book: Book]; log: [book: Book]; delete: [book: Book] }>();

const podcast = computed(() => props.book.kind === 'podcast');
const noun = computed(() => (podcast.value ? 'podcast' : 'book'));
const menu = computed<MenuItem[]>(() => [
    ...(props.book.status === 'finished'
        ? []
        : [
              podcast.value
                  ? { label: 'Log episode', icon: 'headphones' as const, onSelect: () => emit('log', props.book) }
                  : { label: 'Log reading', icon: 'book' as const, onSelect: () => emit('log', props.book) },
          ]),
    { label: `Edit ${noun.value}`, icon: 'edit', onSelect: () => emit('edit', props.book) },
    { label: `Delete ${noun.value}`, icon: 'trash', destructive: true, onSelect: () => emit('delete', props.book) },
]);

const listened = computed(() => {
    const count = props.book.sessions ?? 0;
    const parts = [`${count} ${count === 1 ? 'episode' : 'episodes'}`];
    if (props.book.minutes_read) parts.push(formatMinutes(props.book.minutes_read));
    return parts.join(' · ');
});

const when = computed(() => {
    const { status, finished_on: finished, last_read_on: last, started_on: started } = props.book;
    if (status === 'finished' && finished) return `${podcast.value ? 'Stopped' : 'Finished'} ${formatDate(finished)}`;
    if (last) return `Last ${podcast.value ? 'listened' : 'read'} ${formatDate(last)}`;
    if (started) return `Started ${formatDate(started)}`;
    if (status === 'to_find') return 'Still to find';
    return podcast.value ? 'Not listened yet' : 'On the shelf';
});
</script>

<template>
    <Card v-if="podcast" interactive class="gap-4">
        <div class="flex items-start gap-4">
            <Avatar :name="book.title" size="lg" />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <Heading :level="3" role="title" class="truncate">
                    <RouterLink
                        :to="{ name: 'book-detail', params: { id: book.id } }"
                        class="underline-offset-4 hover:underline"
                        >{{ book.title }}</RouterLink
                    >
                </Heading>
                <Text v-if="book.author" role="label" tone="muted" class="truncate">{{ book.author }}</Text>
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${book.title}”`" size="sm" />
        </div>
        <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-border pt-3">
            <Text role="label" tone="muted" tabular>{{ when }}</Text>
            <Text role="label" tone="muted" tabular>{{ listened }}</Text>
        </div>
    </Card>
    <Card v-else interactive class="min-h-56 gap-3">
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <Badge dot :tone="bookStatusTones[book.status]">{{ statusLabelsFor(book.kind)[book.status] }}</Badge>
                <Badge v-if="book.rating" tone="highlight" icon="star"
                    >{{ book.rating }}/{{ LIMITS.book.rating }}</Badge
                >
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

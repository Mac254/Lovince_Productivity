<script setup lang="ts">
import { LIMITS, type Book } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate, formatMinutes } from '@/lib/dates';
import { bookStatusTones, statusLabelsFor } from '@/lib/labels';
import { linkHandle } from './handle';
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

const handle = computed(() => linkHandle(props.book.link));
const stats = computed(() => [
    { label: props.book.sessions === 1 ? 'Episode' : 'Episodes', value: String(props.book.sessions ?? 0) },
    { label: 'Listened', value: props.book.minutes_read ? formatMinutes(props.book.minutes_read) : '0m' },
    { label: 'Last', value: props.book.last_read_on ? formatDate(props.book.last_read_on) : '—' },
]);

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
        <div class="flex items-center gap-4">
            <Avatar :name="book.title" :src="book.image_url" size="xl" />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <Heading :level="3" role="title" class="truncate">
                    <RouterLink
                        :to="{ name: 'book-detail', params: { id: book.id } }"
                        class="underline-offset-4 hover:underline"
                        >{{ book.title }}</RouterLink
                    >
                </Heading>
                <Text v-if="book.author" role="label" tone="muted" class="truncate">{{ book.author }}</Text>
                <a
                    v-if="handle && book.link"
                    :href="book.link"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-fit max-w-full truncate text-label text-fg-muted underline-offset-4 hover:text-fg hover:underline"
                    >{{ handle }}</a
                >
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${book.title}”`" size="sm" class="self-start" />
        </div>
        <dl class="grid grid-cols-3 gap-3">
            <div v-for="stat in stats" :key="stat.label" class="flex flex-col-reverse gap-1">
                <dt class="text-caption text-fg-muted">{{ stat.label }}</dt>
                <dd class="truncate font-display text-lead font-semibold tabular">{{ stat.value }}</dd>
            </div>
        </dl>
        <div class="mt-auto flex items-center justify-between gap-3 border-t border-border pt-3">
            <Badge dot :tone="bookStatusTones[book.status]">{{ statusLabelsFor(book.kind)[book.status] }}</Badge>
            <Button
                v-if="book.status !== 'finished'"
                variant="outline"
                size="sm"
                icon-start="headphones"
                @click="emit('log', book)"
                >Log episode</Button
            >
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

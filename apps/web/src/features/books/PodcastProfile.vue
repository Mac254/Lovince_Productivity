<script setup lang="ts">
import type { Book, BookReading } from '@focus/types';
import { formatDate, formatMinutes } from '@/lib/dates';
import { bookStatusTones, statusLabelsFor } from '@/lib/labels';
import { linkHandle } from './handle';
import { finished, listeningWeeks } from './listening';
import LessonsNotes from './LessonsNotes.vue';
import ListeningChart from './ListeningChart.vue';

/** A podcast as a profile: who it is, how you've listened, the lessons you've kept and every episode. */
const props = defineProps<{ book: Book }>();
const emit = defineEmits<{ log: []; edit: [reading: BookReading]; remove: [reading: BookReading]; saved: [] }>();

const readings = computed(() => props.book.readings ?? []);
const handle = computed(() => linkHandle(props.book.link));
const lessons = computed(() => readings.value.filter((r) => r.impact));
const lessonCount = computed(
    () => lessons.value.length + (props.book.notes ?? '').split('\n').filter((l) => /^\s*[-*+]\s+\S/.test(l)).length,
);
const weeks = computed(() => listeningWeeks(readings.value));
const thisWeek = computed(() => weeks.value.at(-1)?.minutes ?? 0);
const page = usePagedList(() => readings.value, 8);

const stats = computed(() => [
    { label: readings.value.length === 1 ? 'Episode' : 'Episodes', value: String(readings.value.length) },
    { label: 'Listened', value: formatMinutes(props.book.minutes_read) || '0m' },
    { label: 'To the end', value: String(readings.value.filter(finished).length) },
    { label: lessonCount.value === 1 ? 'Lesson' : 'Lessons', value: String(lessonCount.value) },
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Card as="section" aria-label="Profile" class="gap-6">
            <div class="flex flex-wrap items-center gap-6">
                <Avatar :name="book.title" :src="book.image_url" size="xl" />
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <p class="truncate font-display text-heading font-semibold tracking-tight">{{ book.title }}</p>
                    <Text v-if="book.author" role="label" tone="muted">{{ book.author }}</Text>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <Badge dot :tone="bookStatusTones[book.status]">{{
                            statusLabelsFor(book.kind)[book.status]
                        }}</Badge>
                        <a
                            v-if="handle && book.link"
                            :href="book.link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-label font-semibold text-fg-muted underline-offset-4 hover:text-fg hover:underline"
                            >{{ handle }}</a
                        >
                    </div>
                </div>
                <Button v-if="book.status !== 'finished'" icon-start="headphones" @click="emit('log')"
                    >Log episode</Button
                >
            </div>
            <dl class="grid grid-cols-2 gap-4 border-t border-border pt-6 sm:grid-cols-4">
                <div v-for="stat in stats" :key="stat.label" class="flex flex-col-reverse gap-1">
                    <dt class="text-caption text-fg-muted">{{ stat.label }}</dt>
                    <dd class="font-display text-heading font-semibold tracking-tight tabular">{{ stat.value }}</dd>
                </div>
            </dl>
        </Card>

        <div class="grid gap-6 lg:grid-cols-5">
            <Card as="section" aria-labelledby="listening-heading" class="lg:col-span-3">
                <CardHeader
                    title="Listening"
                    title-id="listening-heading"
                    icon="headphones"
                    :subtitle="`Last 12 weeks · ${formatMinutes(thisWeek) || 'nothing'} this week`"
                />
                <ListeningChart :weeks="weeks" />
            </Card>

            <Card as="section" aria-labelledby="lessons-heading" class="lg:col-span-2">
                <CardHeader title="Lessons learnt" title-id="lessons-heading" icon="lightbulb" />
                <LessonsNotes :book="book" @saved="emit('saved')" />
                <div v-if="lessons.length" class="flex flex-col gap-3 border-t border-border pt-4">
                    <Text role="caption" tone="muted" class="font-semibold">From episodes</Text>
                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="lesson in lessons.slice(0, 5)"
                            :key="lesson.id"
                            class="flex flex-col gap-1 border-l-2 border-highlight pl-4"
                        >
                            <Text role="label" class="whitespace-pre-line">{{ lesson.impact }}</Text>
                            <Text role="caption" tone="muted" class="truncate"
                                >{{ lesson.episode || 'Episode' }} · {{ formatDate(lesson.read_on) }}</Text
                            >
                        </li>
                    </ul>
                </div>
            </Card>
        </div>

        <Card as="section" aria-labelledby="episodes-heading">
            <CardHeader title="Episodes" title-id="episodes-heading" icon="calendar-range" />
            <ol v-if="readings.length" class="flex flex-col divide-y divide-border">
                <li v-for="reading in page.visible" :key="reading.id" class="flex items-start gap-4 py-4 first:pt-0">
                    <span
                        :class="[
                            'mt-1 inline-flex size-8 shrink-0 items-center justify-center rounded-full',
                            finished(reading) ? 'bg-highlight text-highlight-ink' : 'bg-surface-raised text-fg-muted',
                        ]"
                        :title="finished(reading) ? 'Heard to the end' : 'Partly heard'"
                    >
                        <Icon :name="finished(reading) ? 'check' : 'headphones'" />
                    </span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <Text role="label" class="font-semibold">{{ reading.episode || 'Episode' }}</Text>
                        <Text role="caption" tone="muted" tabular
                            >{{ formatDate(reading.read_on, { dateStyle: 'medium' })
                            }}{{ reading.minutes ? ` · ${formatMinutes(reading.minutes)}` : ''
                            }}{{ reading.note && !finished(reading) ? ` · ${reading.note}` : '' }}</Text
                        >
                        <p v-if="reading.impact" class="flex items-start gap-2 pt-1 text-label">
                            <Icon name="lightbulb" class="shrink-0 text-highlight" />
                            <span class="whitespace-pre-line">{{ reading.impact }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            v-if="!reading.impact"
                            variant="outline"
                            size="sm"
                            icon-start="lightbulb"
                            @click="emit('edit', reading)"
                            >Add lesson</Button
                        >
                        <IconButton
                            v-else
                            icon="edit"
                            variant="ghost"
                            size="sm"
                            label="Edit the episode and its lesson"
                            @click="emit('edit', reading)"
                        />
                        <IconButton
                            icon="trash"
                            variant="ghost"
                            size="sm"
                            :label="`Remove the episode on ${formatDate(reading.read_on)}`"
                            @click="emit('remove', reading)"
                        />
                    </div>
                </li>
            </ol>
            <Text v-else role="label" tone="muted">Log each episode with what it said and the lesson you took.</Text>
            <Pagination v-model:page="page.page" :last-page="page.lastPage" />
        </Card>
    </div>
</template>

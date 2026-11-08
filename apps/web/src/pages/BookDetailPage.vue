<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Book, BookReading } from '@focus/types';
import { chapterSummary } from '@/features/books/progress';
import { formatDate, formatMinutes } from '@/lib/dates';
import { bookStatusLabels, bookStatusTones, taskRecurrenceLabels } from '@/lib/labels';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const dashboard = useDashboardStore();

const book = ref<Book | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const editOpen = ref(false);
const logOpen = ref(false);
const taskOpen = ref(false);
const achievementOpen = ref(false);
const deleting = ref(false);
const deleteBusy = ref(false);

const id = computed(() => Number(route.params.id));
const readings = computed(() => book.value?.readings ?? []);
const tasks = computed(() => book.value?.tasks ?? []);
const readingPage = usePagedList(() => readings.value, 10);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        book.value = await api.books.get(id.value);
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this book.');
    } finally {
        loading.value = false;
    }
}

async function changed(): Promise<void> {
    const before = book.value?.status;
    await load();
    void dashboard.invalidate();
    if (before && before !== 'finished' && book.value?.status === 'finished') achievementOpen.value = true;
}

function bookSaved(saved: Book): void {
    const finishedNow = saved.status === 'finished' && book.value?.status !== 'finished';
    void load();
    if (finishedNow) achievementOpen.value = true;
}

async function removeReading(reading: BookReading): Promise<void> {
    try {
        await api.books.removeReading(reading.id);
        toast.success('Reading removed');
        await load();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not remove the reading.'));
    }
}

async function unlinkTask(taskId: number): Promise<void> {
    if (!book.value) return;
    try {
        await api.books.removeTask(book.value.id, taskId);
        toast.success('Task unlinked from the book');
        await load();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not unlink the task.'));
    }
}

async function confirmDelete(): Promise<void> {
    if (!book.value) return;
    deleteBusy.value = true;
    try {
        await api.books.remove(book.value.id);
        toast.success('Book deleted');
        await router.replace({ name: 'books' });
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the book.'));
    } finally {
        deleteBusy.value = false;
    }
}

function sitting(reading: BookReading): string {
    const parts = [];
    if (reading.chapters) parts.push(`${reading.chapters} ${reading.chapters === 1 ? 'chapter' : 'chapters'}`);
    if (reading.minutes) parts.push(formatMinutes(reading.minutes));
    return parts.join(' · ') || 'Note';
}

watch(id, load, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            :title="book?.title ?? 'Book'"
            title-id="page-title"
            :back="{ to: { name: 'books' }, label: 'Back to all books' }"
        >
            <template v-if="book" #eyebrow>
                <Badge dot :tone="bookStatusTones[book.status]">{{ bookStatusLabels[book.status] }}</Badge>
                <Badge v-if="book.rating" tone="highlight" icon="star">{{ book.rating }}/5</Badge>
            </template>
            <template v-if="book?.author" #description>{{ book.author }}</template>
            <template v-if="book" #actions>
                <Button variant="outline" icon-start="edit" @click="editOpen = true">Edit</Button>
                <Button variant="outline" icon-start="trash" @click="deleting = true">Delete</Button>
                <Button v-if="book.status !== 'finished'" icon-start="book" @click="logOpen = true">Log reading</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !book" class="grid gap-4 sm:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
        </div>

        <template v-else-if="book">
            <section aria-label="Book in numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Progress"
                    icon="book"
                    :value="book.progress ?? 0"
                    suffix="%"
                    :hint="chapterSummary(book)"
                    emphasis
                />
                <StatCard
                    label="Sittings"
                    icon="calendar-range"
                    :value="readings.length"
                    :hint="book.last_read_on ? `Last on ${formatDate(book.last_read_on)}` : 'None logged yet'"
                />
                <StatCard
                    label="Time read"
                    icon="clock"
                    :value="Math.round((book.minutes_read ?? 0) / 6) / 10"
                    suffix="h"
                    hint="From logged sittings"
                />
                <StatCard label="Open tasks" icon="tasks" :value="book.open_tasks ?? 0" hint="Reading tasks to do" />
            </section>

            <Card as="div" class="gap-3">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <p class="font-display text-lead font-medium">
                        <RouterLink
                            v-if="book.goal"
                            :to="{ name: 'goal-detail', params: { id: book.goal.id } }"
                            class="underline-offset-4 hover:underline"
                            >{{ book.goal.title }}</RouterLink
                        >
                        <template v-else>Reading</template>
                    </p>
                    <Text role="label" tone="muted" tabular>
                        {{ book.started_on ? formatDate(book.started_on, { dateStyle: 'medium' }) : 'Not started' }} →
                        {{ book.finished_on ? formatDate(book.finished_on, { dateStyle: 'medium' }) : 'Not finished' }}
                    </Text>
                </div>
                <ProgressBar
                    v-if="book.progress !== null"
                    :value="book.progress"
                    :label="`${book.title} progress`"
                    :tone="book.progress === 100 ? 'highlight' : 'accent'"
                />
                <Text v-else role="label" tone="muted">Add the number of chapters to see progress.</Text>
            </Card>

            <Card v-if="book.notes" as="div" class="gap-2">
                <p class="font-display text-lead font-medium">Notes</p>
                <Text class="whitespace-pre-line">{{ book.notes }}</Text>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <section aria-labelledby="book-readings" class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="book-readings" class="font-display text-title font-semibold tracking-tight">
                            Reading log
                        </h2>
                        <Button
                            v-if="book.status !== 'finished'"
                            variant="outline"
                            icon-start="book"
                            @click="logOpen = true"
                            >Log reading</Button
                        >
                    </div>
                    <Card as="div" class="gap-1">
                        <ul v-if="readings.length" class="flex flex-col divide-y divide-border">
                            <li
                                v-for="reading in readingPage.visible"
                                :key="reading.id"
                                class="flex items-start justify-between gap-3 py-3"
                            >
                                <div class="flex min-w-0 flex-col gap-1">
                                    <Text role="label" tabular class="font-medium">{{ sitting(reading) }}</Text>
                                    <Text role="label" tone="muted" tabular>{{
                                        formatDate(reading.read_on, { dateStyle: 'medium' })
                                    }}</Text>
                                    <Text v-if="reading.note" role="label" tone="muted" class="whitespace-pre-line">{{
                                        reading.note
                                    }}</Text>
                                </div>
                                <IconButton
                                    icon="trash"
                                    variant="ghost"
                                    size="sm"
                                    :label="`Remove the reading on ${formatDate(reading.read_on)}`"
                                    @click="removeReading(reading)"
                                />
                            </li>
                        </ul>
                        <Text v-else role="label" tone="muted" class="py-2"
                            >Log each sitting to track chapters and time.</Text
                        >
                        <Pagination v-model:page="readingPage.page" :last-page="readingPage.lastPage" />
                    </Card>
                </section>

                <section aria-labelledby="book-tasks" class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="book-tasks" class="font-display text-title font-semibold tracking-tight">Tasks</h2>
                        <Button variant="outline" icon-start="add" @click="taskOpen = true">Add reading task</Button>
                    </div>
                    <Card as="div" class="gap-1">
                        <ul v-if="tasks.length" class="flex flex-col divide-y divide-border">
                            <li
                                v-for="task in tasks"
                                :key="task.id"
                                class="flex items-center justify-between gap-3 py-3"
                            >
                                <div class="flex min-w-0 flex-col gap-1">
                                    <RouterLink
                                        :to="{ name: 'task-detail', params: { id: task.id } }"
                                        class="truncate font-medium underline-offset-4 hover:underline"
                                        >{{ task.title }}</RouterLink
                                    >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <StatusBadge :status="task.status" />
                                        <Text v-if="task.recurrence" as="span" role="label" tone="muted">{{
                                            taskRecurrenceLabels[task.recurrence]
                                        }}</Text>
                                        <Text v-if="task.due_date" as="span" role="label" tone="muted" tabular
                                            >Due {{ formatDate(task.due_date) }}</Text
                                        >
                                    </div>
                                </div>
                                <IconButton
                                    icon="close"
                                    variant="ghost"
                                    size="sm"
                                    :label="`Unlink “${task.title}” from this book`"
                                    @click="unlinkTask(task.id)"
                                />
                            </li>
                        </ul>
                        <Text v-else role="label" tone="muted" class="py-2"
                            >Reading tasks — a daily chapter, a weekend session — show up here.</Text
                        >
                    </Card>
                </section>
            </div>
        </template>

        <BookFormDialog v-model:open="editOpen" :book="book" @saved="bookSaved" />
        <ReadingLogDialog v-model:open="logOpen" :book="book" @saved="changed" />
        <BookTaskDialog v-model:open="taskOpen" :book="book" @saved="load" />
        <AchievementFormDialog
            v-model:open="achievementOpen"
            :defaults="{ goal_id: book?.goal_id ?? undefined, title: book ? `Finished reading ${book.title}` : '' }"
            @saved="load"
        />
        <ConfirmDialog
            v-model:open="deleting"
            title="Delete book?"
            :message="`“${book?.title}” and its reading log will be deleted. Its tasks are kept and simply unlinked.`"
            :loading="deleteBusy"
            @confirm="confirmDelete"
        />
    </div>
</template>

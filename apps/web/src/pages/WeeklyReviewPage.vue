<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Task, type WeeklyReview, type WeeklyReviewSummary } from '@focus/types';
import { weekLabel, weekStartIso } from '@/features/reviews/week';
import { shiftDate } from '@/lib/calendar';
import { formatDate, formatMinutes, todayIso } from '@/lib/dates';
import { toneDot } from '@/lib/tones';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const dashboard = useDashboardStore();

const review = ref<WeeklyReview | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);
const moving = ref<number | null>(null);
const movingAll = ref(false);
const draft = reactive({ wins: '', challenges: '', next_focus: '' });
const past = ref<WeeklyReviewSummary[]>([]);

const thisWeek = weekStartIso();
const week = computed(() =>
    weekStartIso(typeof route.params.week === 'string' && route.params.week ? route.params.week : todayIso()),
);
const isThisWeek = computed(() => week.value === thisWeek);
const done = computed(() => !!review.value?.review?.completed_at);
const dirty = computed(() => {
    const saved = review.value?.review;
    return (
        draft.wins !== (saved?.wins ?? '') ||
        draft.challenges !== (saved?.challenges ?? '') ||
        draft.next_focus !== (saved?.next_focus ?? '')
    );
});
const peak = computed(() =>
    Math.max(1, ...(review.value?.days ?? []).map((day) => day.focus_minutes + day.minutes_logged)),
);

function fill(data: WeeklyReview): void {
    review.value = data;
    draft.wins = data.review?.wins ?? '';
    draft.challenges = data.review?.challenges ?? '';
    draft.next_focus = data.review?.next_focus ?? '';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        fill(await api.reviews.get(week.value));
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this week.');
    } finally {
        loading.value = false;
    }
}

async function loadPast(): Promise<void> {
    try {
        past.value = await api.reviews.list();
    } catch {
        past.value = [];
    }
}

async function save(completed?: boolean): Promise<void> {
    saving.value = true;
    try {
        fill(await api.reviews.save(week.value, { ...draft, ...(completed === undefined ? {} : { completed }) }));
        toast.success(
            completed ? 'Review done. Have a good week.' : completed === false ? 'Review reopened' : 'Review saved',
        );
        if (completed !== undefined) {
            void dashboard.load();
            void loadPast();
        }
    } catch (e) {
        toast.error(errorMessage(e, 'Could not save the review.'));
    } finally {
        saving.value = false;
    }
}

async function moveToNextWeek(task: Task): Promise<void> {
    if (!review.value) return;
    moving.value = task.id;
    try {
        await api.tasks.update(task.id, { due_date: review.value.week.next_start });
        await load();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not move the task.'));
    } finally {
        moving.value = null;
    }
}

async function moveAll(): Promise<void> {
    if (!review.value) return;
    movingAll.value = true;
    const next = review.value.week.next_start;
    try {
        await Promise.all(review.value.carry_over.map((task) => api.tasks.update(task.id, { due_date: next })));
        toast.success('Moved to next week');
        await load();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not move every task.'));
        await load();
    } finally {
        movingAll.value = false;
    }
}

function go(start: string): void {
    void router.replace({ name: 'review', params: start === thisWeek ? {} : { week: start } });
}

watch(week, load, { immediate: true });
void loadPast();

const plural = (count: number, one: string, many = `${one}s`) => `${count} ${count === 1 ? one : many}`;
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Weekly review"
            title-id="page-title"
            :description="review ? weekLabel(review.week.start, review.week.end) : undefined"
        >
            <template #actions>
                <IconButton
                    icon="chevron-left"
                    label="Previous week"
                    variant="outline"
                    motion="nudge-back"
                    @click="go(shiftDate(week, -7))"
                />
                <IconButton
                    icon="chevron-right"
                    label="Next week"
                    variant="outline"
                    motion="nudge"
                    :disabled="isThisWeek"
                    @click="go(shiftDate(week, 7))"
                />
                <Button v-if="!isThisWeek" variant="ghost" @click="go(thisWeek)">This week</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <Skeleton v-else-if="loading && !review" class="h-96" />

        <template v-else-if="review">
            <section aria-label="Week in numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Completed"
                    icon="check-circle"
                    :value="review.totals.completed"
                    hint="Tasks done"
                    emphasis
                />
                <StatCard
                    label="Focus"
                    icon="hourglass"
                    :value="Math.round(review.totals.focus_minutes / 6) / 10"
                    suffix="h"
                    :hint="plural(review.totals.focus_sessions, 'session')"
                />
                <StatCard
                    label="Time logged"
                    icon="history"
                    :value="Math.round(review.totals.minutes_logged / 6) / 10"
                    suffix="h"
                    hint="On task work logs"
                />
                <StatCard
                    label="Blockers"
                    icon="blocker"
                    motion="wiggle"
                    :value="review.totals.blockers_open"
                    :hint="`${review.totals.blockers_opened} raised · ${review.totals.blockers_resolved} resolved`"
                />
            </section>

            <Card as="section" aria-labelledby="days-heading" class="group">
                <CardHeader
                    title="Day by day"
                    title-id="days-heading"
                    icon="calendar-range"
                    subtitle="Focus and logged time"
                />
                <ol class="grid grid-cols-7 gap-2">
                    <li v-for="day in review.days" :key="day.date" class="flex flex-col items-center gap-2">
                        <div class="flex h-24 w-full items-end justify-center rounded-md bg-surface-raised p-1">
                            <div
                                class="w-full rounded-sm bg-accent"
                                :style="{ height: `${((day.focus_minutes + day.minutes_logged) / peak) * 100}%` }"
                                :title="`${formatMinutes(day.focus_minutes) || '0m'} focus · ${formatMinutes(day.minutes_logged) || '0m'} logged`"
                            />
                        </div>
                        <Text role="caption" weight="semibold">{{ formatDate(day.date, { weekday: 'short' }) }}</Text>
                        <Text role="caption" tone="muted" tabular>{{ plural(day.completed, 'done', 'done') }}</Text>
                    </li>
                </ol>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card as="section" aria-labelledby="done-heading" class="group">
                    <CardHeader
                        title="Completed"
                        title-id="done-heading"
                        icon="check-circle"
                        :subtitle="plural(review.completed.length, 'task')"
                    />
                    <ReviewTaskList :tasks="review.completed" empty="Nothing was completed this week." />
                </Card>

                <Card as="section" aria-labelledby="carry-heading" class="group">
                    <CardHeader
                        title="Carry over"
                        title-id="carry-heading"
                        icon="history"
                        subtitle="Due by the week’s end, still open"
                    >
                        <Button
                            v-if="review.carry_over.length > 1"
                            variant="ghost"
                            size="sm"
                            :loading="movingAll"
                            @click="moveAll"
                            >Move all to next week</Button
                        >
                    </CardHeader>
                    <ReviewTaskList
                        :tasks="review.carry_over"
                        empty="Nothing carries over."
                        action="Next week"
                        :busy="moving"
                        @act="moveToNextWeek"
                    />
                </Card>

                <Card as="section" aria-labelledby="goals-heading" class="group">
                    <CardHeader title="Goals" title-id="goals-heading" icon="target" subtitle="Active goals" />
                    <ul v-if="review.goals.length" class="flex flex-col gap-4">
                        <li v-for="goal in review.goals" :key="goal.id" class="flex flex-col gap-2">
                            <span class="flex items-center justify-between gap-3">
                                <RouterLink
                                    :to="{ name: 'goal-detail', params: { id: goal.id } }"
                                    class="flex min-w-0 items-center gap-2 font-semibold underline-offset-4 hover:underline"
                                    ><span :class="['size-2 shrink-0 rounded-full', toneDot[goal.tone]]" /><span
                                        class="truncate"
                                        >{{ goal.title }}</span
                                    ></RouterLink
                                >
                                <Text as="span" role="caption" tone="muted" tabular
                                    >{{ goal.completed }}/{{ goal.total }} ·
                                    {{
                                        goal.completed_this_week
                                            ? `+${goal.completed_this_week} this week`
                                            : 'no progress this week'
                                    }}</Text
                                >
                            </span>
                            <ProgressBar
                                :value="goal.progress"
                                :label="`${goal.title} progress`"
                                :tone="goal.progress === 100 ? 'highlight' : 'accent'"
                            />
                        </li>
                    </ul>
                    <Text v-else role="label" tone="muted">No active goals.</Text>
                </Card>

                <Card as="section" aria-labelledby="next-heading" class="group">
                    <CardHeader
                        title="Next week"
                        title-id="next-heading"
                        icon="calendar-check"
                        :subtitle="weekLabel(review.week.next_start, review.week.next_end)"
                    />
                    <ReviewTaskList
                        :tasks="review.next_week"
                        empty="Nothing is due next week yet."
                        :today="todayIso()"
                    />
                    <template v-if="review.unscheduled.length">
                        <p class="mt-4 mb-1 text-label font-semibold">Unscheduled</p>
                        <ReviewTaskList
                            :tasks="review.unscheduled"
                            empty=""
                            action="Plan"
                            :busy="moving"
                            @act="moveToNextWeek"
                        />
                    </template>
                </Card>
            </div>

            <Card v-if="review.journal.length" as="section" aria-labelledby="journal-heading" class="group">
                <CardHeader title="From your journal" title-id="journal-heading" icon="journal" />
                <ul class="flex flex-col divide-y divide-border">
                    <li v-for="entry in review.journal" :key="entry.date" class="flex flex-col gap-1 py-2">
                        <RouterLink
                            :to="{ name: 'journal', query: { date: entry.date } }"
                            class="text-label font-semibold underline-offset-4 hover:underline"
                            >{{
                                formatDate(entry.date, { weekday: 'long', day: 'numeric', month: 'short' })
                            }}</RouterLink
                        >
                        <Text role="label" tone="muted" class="break-words">{{ entry.excerpt }}</Text>
                    </li>
                </ul>
            </Card>

            <Card as="section" aria-labelledby="reflect-heading" class="group gap-4">
                <CardHeader
                    title="Reflect"
                    title-id="reflect-heading"
                    icon="edit"
                    :subtitle="
                        done
                            ? `Done ${formatDate(review.review!.completed_at!, { dateStyle: 'medium' })}`
                            : 'A few lines is enough'
                    "
                />
                <div class="grid gap-4 lg:grid-cols-3">
                    <Textarea
                        id="review-wins"
                        v-model="draft.wins"
                        label="What went well"
                        optional
                        :maxlength="LIMITS.review"
                    />
                    <Textarea
                        id="review-challenges"
                        v-model="draft.challenges"
                        label="What got in the way"
                        optional
                        :maxlength="LIMITS.review"
                    />
                    <Textarea
                        id="review-next"
                        v-model="draft.next_focus"
                        label="Next week’s focus"
                        optional
                        :maxlength="LIMITS.review"
                    />
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <Button variant="outline" icon-start="save" :disabled="!dirty" :loading="saving" @click="save()"
                        >Save</Button
                    >
                    <Button v-if="!done" icon-start="check" :loading="saving" @click="save(true)">Finish review</Button>
                    <Button v-else variant="ghost" :loading="saving" @click="save(false)">Reopen</Button>
                </div>
            </Card>

            <Card v-if="past.length" as="section" aria-labelledby="past-heading" class="group">
                <CardHeader title="Past reviews" title-id="past-heading" icon="history" subtitle="Weeks you finished" />
                <ul class="flex flex-wrap gap-2">
                    <li v-for="item in past" :key="item.week_start">
                        <Button
                            variant="outline"
                            size="sm"
                            :to="{ name: 'review', params: { week: item.week_start } }"
                            :aria-current="item.week_start === week ? 'page' : undefined"
                        >
                            {{ weekLabel(item.week_start, shiftDate(item.week_start, 6)) }}
                        </Button>
                    </li>
                </ul>
            </Card>
        </template>
    </div>
</template>

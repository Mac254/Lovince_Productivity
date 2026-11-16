<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Review, type ReviewPeriod, type ReviewSummary, type Task } from '@focus/types';
import type { TabItem } from '@focus/ui';
import { PERIOD_NAMES, periodLabel, periodStart, shiftPeriod } from '@/features/reviews/period';
import { formatDate, formatMinutes, todayIso } from '@/lib/dates';
import { toneDot } from '@/lib/tones';

type Section = 'days' | 'tasks' | 'plan' | 'shorter' | 'journal' | 'past';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const outbox = useOutboxStore();
const dashboard = useDashboardStore();

const review = ref<Review | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);
const moving = ref<number | null>(null);
const movingAll = ref(false);
const draft = reactive({ wins: '', challenges: '', next_focus: '' });
const past = ref<ReviewSummary[]>([]);
const reflecting = ref(false);

const periodOptions = [
    { value: 'week' as const, label: 'Week' },
    { value: 'month' as const, label: 'Month' },
    { value: 'year' as const, label: 'Year' },
];
const period = computed<ReviewPeriod>(() =>
    route.params.period === 'month' || route.params.period === 'year' ? route.params.period : 'week',
);
const name = computed(() => PERIOD_NAMES[period.value]);
const shorterName = computed(() => (period.value === 'year' ? 'month' : 'week'));
const start = computed(() =>
    periodStart(
        period.value,
        typeof route.params.start === 'string' && route.params.start ? route.params.start : todayIso(),
    ),
);
const current = computed(() => periodStart(period.value));
const isCurrent = computed(() => start.value === current.value);

const tab = useTabQuery(['days', 'tasks', 'plan', 'shorter', 'journal', 'past'] as const, 'days');
const tabs = computed<TabItem<Section>[]>(() => [
    { value: 'days', label: period.value === 'year' ? 'Months' : 'Days', icon: 'calendar-range' },
    { value: 'tasks', label: 'Tasks', icon: 'check-circle', count: review.value?.carry_over.length },
    { value: 'plan', label: `Goals & next ${name.value}`, icon: 'target' },
    ...(period.value === 'week'
        ? []
        : [
              {
                  value: 'shorter' as const,
                  label: `${shorterName.value === 'week' ? 'Weekly' : 'Monthly'} reviews`,
                  icon: 'history' as const,
                  count: review.value?.shorter.length,
              },
          ]),
    { value: 'journal', label: 'Journal', icon: 'journal', count: review.value?.journal.length },
    { value: 'past', label: 'Past reviews', icon: 'history' },
]);
watch(period, (kind) => {
    if (kind === 'week' && tab.value === 'shorter') tab.value = 'days';
});

const completedPage = usePagedList(() => review.value?.completed ?? []);
const carryPage = usePagedList(() => review.value?.carry_over ?? []);
const skippedPage = usePagedList(() => review.value?.skipped ?? []);
const goalsPage = usePagedList(() => review.value?.goals ?? [], 6);
const nextPage = usePagedList(() => review.value?.next ?? []);
const unscheduledPage = usePagedList(() => review.value?.unscheduled ?? [], 5);
const shorterPage = usePagedList(() => review.value?.shorter ?? [], 4);
const pastPage = usePagedList(past, 24);

const done = computed(() => !!review.value?.review?.completed_at);
const written = computed(() => {
    const saved = review.value?.review;
    return !!(saved?.wins || saved?.challenges || saved?.next_focus);
});
const dirty = computed(() => {
    const saved = review.value?.review;
    return (
        draft.wins !== (saved?.wins ?? '') ||
        draft.challenges !== (saved?.challenges ?? '') ||
        draft.next_focus !== (saved?.next_focus ?? '')
    );
});
const peak = computed(() =>
    Math.max(1, ...(review.value?.buckets ?? []).map((bucket) => bucket.focus_minutes + bucket.minutes_logged)),
);
const completedSubtitle = computed(() => {
    if (!review.value) return '';
    const total = review.value.totals.completed;
    const shown = review.value.completed.length;
    return shown < total ? `Latest ${shown} of ${plural(total, 'task')}` : plural(total, 'task');
});

function fill(data: Review): void {
    review.value = data;
    draft.wins = data.review?.wins ?? '';
    draft.challenges = data.review?.challenges ?? '';
    draft.next_focus = data.review?.next_focus ?? '';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        fill(await api.reviews.get(period.value, start.value));
    } catch (e) {
        error.value = errorMessage(e, `Could not load this ${name.value}.`);
    } finally {
        loading.value = false;
    }
}

async function loadPast(): Promise<void> {
    try {
        past.value = await api.reviews.list(period.value);
    } catch {
        past.value = [];
    }
}

async function save(completed?: boolean): Promise<void> {
    saving.value = true;
    try {
        fill(
            await api.reviews.save(period.value, start.value, {
                ...draft,
                ...(completed === undefined ? {} : { completed }),
            }),
        );
        toast.success(
            completed
                ? `Review done. Have a good ${name.value}.`
                : completed === false
                  ? 'Review reopened'
                  : 'Review saved',
        );
        if (completed !== undefined) {
            reflecting.value = false;
            void dashboard.load();
            void loadPast();
        }
    } catch (e) {
        toast.error(errorMessage(e, 'Could not save the review.'));
    } finally {
        saving.value = false;
    }
}

async function moveToNext(task: Task): Promise<void> {
    if (!review.value) return;
    moving.value = task.id;
    const next = review.value.period.next_start;
    try {
        await api.tasks.update(task.id, { due_date: next });
        await load();
    } catch (e) {
        if (isOffline(e)) {
            outbox.queueTaskUpdate(task, { due_date: next });
            toast.info(`You’re offline. “${task.title}” will move when you’re back.`);
            return;
        }
        toast.error(errorMessage(e, 'Could not move the task.'));
    } finally {
        moving.value = null;
    }
}

async function moveAll(): Promise<void> {
    if (!review.value) return;
    movingAll.value = true;
    const next = review.value.period.next_start;
    try {
        await Promise.all(review.value.carry_over.map((task) => api.tasks.update(task.id, { due_date: next })));
        toast.success(`Moved to next ${name.value}`);
        await load();
    } catch (e) {
        if (isOffline(e)) {
            review.value.carry_over.forEach((task) => outbox.queueTaskUpdate(task, { due_date: next }));
            toast.info('You’re offline. The tasks will move when you’re back.');
            return;
        }
        toast.error(errorMessage(e, 'Could not move every task.'));
        await load();
    } finally {
        movingAll.value = false;
    }
}

function go(kind: ReviewPeriod, date: string): void {
    const first = periodStart(kind, date);
    const params: Record<string, string> = {};
    if (kind !== 'week') params.period = kind;
    if (first !== periodStart(kind)) params.start = first;
    void router.replace({ name: 'review', params, query: route.query });
}

const choosePeriod = computed({
    get: () => period.value,
    set: (kind: ReviewPeriod) => go(kind, start.value),
});

function bucketCaption(index: number, date: string): string {
    if (period.value === 'year') return formatDate(date, { month: 'short' });
    if (period.value === 'week') return formatDate(date, { weekday: 'short' });
    return index % 7 === 0 ? formatDate(date, { day: 'numeric' }) : '';
}

watch([period, start], load, { immediate: true });
watch(period, loadPast, { immediate: true });

const plural = (count: number, one: string, many = `${one}s`) => `${count} ${count === 1 ? one : many}`;
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Review"
            title-id="page-title"
            :description="review ? periodLabel(period, review.period.start, review.period.end) : undefined"
        >
            <template #actions>
                <SegmentedControl v-model="choosePeriod" label="Review period" :options="periodOptions" size="sm" />
                <IconButton
                    icon="chevron-left"
                    :label="`Previous ${name}`"
                    variant="outline"
                    motion="nudge-back"
                    @click="go(period, shiftPeriod(period, start, -1))"
                />
                <IconButton
                    icon="chevron-right"
                    :label="`Next ${name}`"
                    variant="outline"
                    motion="nudge"
                    :disabled="isCurrent"
                    @click="go(period, shiftPeriod(period, start, 1))"
                />
                <Button v-if="!isCurrent" variant="ghost" @click="go(period, current)">This {{ name }}</Button>
                <Button
                    v-if="review"
                    :variant="done ? 'outline' : 'primary'"
                    icon-start="edit"
                    @click="reflecting = true"
                    >{{ written ? 'Edit reflection' : 'Reflect' }}</Button
                >
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <Skeleton v-else-if="loading && !review" class="h-96" />

        <template v-else-if="review">
            <section
                :aria-label="`${name.charAt(0).toUpperCase()}${name.slice(1)} in numbers`"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
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

            <Card as="section" aria-labelledby="reflection-heading" class="group">
                <CardHeader
                    title="Your reflection"
                    title-id="reflection-heading"
                    icon="journal"
                    :subtitle="
                        done
                            ? `Finished ${formatDate(review.review!.completed_at!, { dateStyle: 'medium' })}`
                            : written
                              ? 'Draft, not finished yet'
                              : `How did the ${name} go?`
                    "
                >
                    <Button variant="ghost" size="sm" icon-start="edit" @click="reflecting = true">{{
                        written ? 'Edit' : 'Write'
                    }}</Button>
                </CardHeader>
                <div v-if="written" class="grid gap-6 lg:grid-cols-3">
                    <section aria-labelledby="wins-heading" class="flex flex-col gap-3">
                        <Text id="wins-heading" as="h3" role="label" weight="semibold">What went well</Text>
                        <ReflectionText :text="review.review?.wins ?? null" />
                    </section>
                    <section aria-labelledby="challenges-heading" class="flex flex-col gap-3">
                        <Text id="challenges-heading" as="h3" role="label" weight="semibold">What got in the way</Text>
                        <ReflectionText :text="review.review?.challenges ?? null" />
                    </section>
                    <section aria-labelledby="focus-heading" class="flex flex-col gap-3">
                        <Text id="focus-heading" as="h3" role="label" weight="semibold">Next {{ name }}’s focus</Text>
                        <ReflectionText :text="review.review?.next_focus ?? null" />
                    </section>
                </div>
                <Text v-else role="label" tone="muted"
                    >Nothing written for this {{ name }} yet. A few lines is enough.</Text
                >
            </Card>

            <Tabs v-model="tab" :label="`${name} sections`" :tabs="tabs">
                <template #days>
                    <Card as="section" aria-labelledby="days-heading" class="group">
                        <CardHeader
                            :title="period === 'year' ? 'Month by month' : 'Day by day'"
                            title-id="days-heading"
                            icon="calendar-range"
                            subtitle="Focus and logged time"
                        />
                        <ol :class="['flex', period === 'month' ? 'gap-1' : 'gap-2']">
                            <li
                                v-for="(bucket, index) in review.buckets"
                                :key="bucket.start"
                                class="flex min-w-0 flex-1 flex-col items-center gap-2"
                            >
                                <div class="flex h-24 w-full items-end justify-center rounded-md bg-surface-raised p-1">
                                    <div
                                        class="w-full rounded-sm bg-accent"
                                        :style="{
                                            height: `${((bucket.focus_minutes + bucket.minutes_logged) / peak) * 100}%`,
                                        }"
                                        :title="`${formatDate(bucket.start, period === 'year' ? { month: 'long' } : { weekday: 'short', day: 'numeric', month: 'short' })}: ${formatMinutes(bucket.focus_minutes) || '0m'} focus · ${formatMinutes(bucket.minutes_logged) || '0m'} logged · ${bucket.completed} done`"
                                    />
                                </div>
                                <Text role="caption" weight="semibold" class="h-4 truncate">{{
                                    bucketCaption(index, bucket.start)
                                }}</Text>
                                <Text v-if="period !== 'month'" role="caption" tone="muted" tabular>{{
                                    plural(bucket.completed, 'done', 'done')
                                }}</Text>
                            </li>
                        </ol>
                    </Card>
                </template>

                <template #tasks>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="done-heading" class="group">
                            <CardHeader
                                title="Completed"
                                title-id="done-heading"
                                icon="check-circle"
                                :subtitle="completedSubtitle"
                            />
                            <ReviewTaskList
                                :tasks="completedPage.visible"
                                :empty="`Nothing was completed this ${name}.`"
                            />
                            <Pagination v-model:page="completedPage.page" :last-page="completedPage.lastPage" />
                        </Card>

                        <Card as="section" aria-labelledby="carry-heading" class="group">
                            <CardHeader
                                title="Carry over"
                                title-id="carry-heading"
                                icon="history"
                                :subtitle="`Due by the ${name}’s end, still open`"
                            >
                                <Button
                                    v-if="review.carry_over.length > 1"
                                    variant="ghost"
                                    size="sm"
                                    :loading="movingAll"
                                    @click="moveAll"
                                    >Move all to next {{ name }}</Button
                                >
                            </CardHeader>
                            <ReviewTaskList
                                :tasks="carryPage.visible"
                                empty="Nothing carries over."
                                :action="`Next ${name}`"
                                :busy="moving"
                                @act="moveToNext"
                            />
                            <Pagination v-model:page="carryPage.page" :last-page="carryPage.lastPage" />
                        </Card>
                        <Card v-if="review.skipped.length" as="section" aria-labelledby="skipped-heading" class="group">
                            <CardHeader
                                title="Skipped"
                                title-id="skipped-heading"
                                icon="close-circle"
                                :subtitle="plural(review.skipped.length, 'task')"
                            />
                            <ul class="flex flex-col divide-y divide-border">
                                <li v-for="task in skippedPage.visible" :key="task.id" class="flex flex-col gap-1 py-2">
                                    <RouterLink
                                        :to="{ name: 'task-detail', params: { id: task.id } }"
                                        class="min-w-0 truncate font-semibold underline-offset-4 hover:underline"
                                        >{{ task.title }}</RouterLink
                                    >
                                    <Text as="span" role="label" tone="muted">{{
                                        task.cancel_reason ?? 'No reason given'
                                    }}</Text>
                                </li>
                            </ul>
                            <Pagination v-model:page="skippedPage.page" :last-page="skippedPage.lastPage" />
                        </Card>
                    </div>
                </template>

                <template #plan>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="goals-heading" class="group">
                            <CardHeader title="Goals" title-id="goals-heading" icon="target" subtitle="Active goals" />
                            <ul v-if="review.goals.length" class="flex flex-col gap-4">
                                <li v-for="goal in goalsPage.visible" :key="goal.id" class="flex flex-col gap-2">
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
                                                goal.completed_in_period
                                                    ? `+${goal.completed_in_period} this ${name}`
                                                    : `no progress this ${name}`
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
                            <Pagination v-model:page="goalsPage.page" :last-page="goalsPage.lastPage" />
                        </Card>

                        <Card as="section" aria-labelledby="next-heading" class="group">
                            <CardHeader
                                :title="`Next ${name}`"
                                title-id="next-heading"
                                icon="calendar-check"
                                :subtitle="periodLabel(period, review.period.next_start, review.period.next_end)"
                            />
                            <ReviewTaskList
                                :tasks="nextPage.visible"
                                :empty="`Nothing is due next ${name} yet.`"
                                :today="todayIso()"
                            />
                            <Pagination v-model:page="nextPage.page" :last-page="nextPage.lastPage" />
                            <template v-if="review.unscheduled.length">
                                <p class="mt-4 mb-1 text-label font-semibold">Unscheduled</p>
                                <ReviewTaskList
                                    :tasks="unscheduledPage.visible"
                                    empty=""
                                    action="Plan"
                                    :busy="moving"
                                    @act="moveToNext"
                                />
                                <Pagination v-model:page="unscheduledPage.page" :last-page="unscheduledPage.lastPage" />
                            </template>
                        </Card>
                    </div>
                </template>

                <template #shorter>
                    <div class="flex flex-col gap-6">
                        <Card
                            v-for="item in shorterPage.visible"
                            :key="item.start"
                            as="section"
                            :aria-labelledby="`shorter-${item.start}`"
                            class="group"
                        >
                            <CardHeader
                                :title="item.label"
                                :title-id="`shorter-${item.start}`"
                                icon="history"
                                :subtitle="item.completed_at ? 'Finished' : 'Draft'"
                            >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    icon-end="arrow-right"
                                    :to="{
                                        name: 'review',
                                        params:
                                            item.period === 'week'
                                                ? { start: item.start }
                                                : { period: item.period, start: item.start },
                                    }"
                                    >Open</Button
                                >
                            </CardHeader>
                            <div class="grid gap-6 lg:grid-cols-3">
                                <div class="flex flex-col gap-2">
                                    <Text as="h4" role="caption" tone="muted" weight="semibold">What went well</Text>
                                    <ReflectionText :text="item.wins" empty="—" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Text as="h4" role="caption" tone="muted" weight="semibold"
                                        >What got in the way</Text
                                    >
                                    <ReflectionText :text="item.challenges" empty="—" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Text as="h4" role="caption" tone="muted" weight="semibold">Focus after</Text>
                                    <ReflectionText :text="item.next_focus" empty="—" />
                                </div>
                            </div>
                        </Card>
                        <Card v-if="!review.shorter.length" as="section" class="group">
                            <Text role="label" tone="muted"
                                >No {{ shorterName }}ly reviews written in this {{ name }} yet.</Text
                            >
                        </Card>
                        <Pagination v-model:page="shorterPage.page" :last-page="shorterPage.lastPage" />
                    </div>
                </template>

                <template #journal>
                    <Card as="section" aria-labelledby="journal-heading" class="group">
                        <CardHeader
                            title="From your journal"
                            title-id="journal-heading"
                            icon="journal"
                            :subtitle="period === 'week' ? undefined : 'Latest entries'"
                        />
                        <ul v-if="review.journal.length" class="flex flex-col divide-y divide-border">
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
                        <Text v-else role="label" tone="muted">No journal entries this {{ name }}.</Text>
                    </Card>
                </template>

                <template #past>
                    <Card as="section" aria-labelledby="past-heading" class="group">
                        <CardHeader
                            title="Past reviews"
                            title-id="past-heading"
                            icon="history"
                            :subtitle="`${name.charAt(0).toUpperCase()}${name.slice(1)}s you finished`"
                        />
                        <ul v-if="past.length" class="flex flex-wrap gap-2">
                            <li v-for="item in pastPage.visible" :key="item.start">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :to="{
                                        name: 'review',
                                        params:
                                            item.period === 'week'
                                                ? { start: item.start }
                                                : { period: item.period, start: item.start },
                                    }"
                                    :aria-current="item.start === start ? 'page' : undefined"
                                >
                                    {{ item.label }}
                                </Button>
                            </li>
                        </ul>
                        <Text v-else role="label" tone="muted">No finished reviews yet.</Text>
                        <Pagination v-model:page="pastPage.page" :last-page="pastPage.lastPage" />
                    </Card>
                </template>
            </Tabs>
        </template>
    </div>

    <Dialog
        v-if="review"
        v-model:open="reflecting"
        :title="`Reflect on the ${name}`"
        :description="
            done
                ? `Done ${formatDate(review.review!.completed_at!, { dateStyle: 'medium' })}`
                : 'A few lines is enough. Start a line with - or 1. to make a list.'
        "
        initial-focus="review-wins"
    >
        <div class="flex flex-col gap-4">
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
                :label="`Next ${name}’s focus`"
                optional
                :maxlength="LIMITS.review"
            />
        </div>
        <template #footer>
            <Button variant="outline" icon-start="save" :disabled="!dirty" :loading="saving" @click="save()"
                >Save</Button
            >
            <Button v-if="!done" icon-start="check" :loading="saving" @click="save(true)">Finish review</Button>
            <Button v-else variant="ghost" :loading="saving" @click="save(false)">Reopen</Button>
        </template>
    </Dialog>
</template>

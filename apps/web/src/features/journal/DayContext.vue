<script setup lang="ts">
import type { IconName } from '@focus/ui';
import type { JournalContext } from '@focus/types';
import { formatMinutes } from '@/lib/dates';

/** What happened on the day being written about, with a one-click stand-up draft built from it. */
const props = defineProps<{ context: JournalContext | null; loading: boolean; isToday: boolean }>();
defineEmits<{ draft: [] }>();

const minutes = computed(() => (props.context?.work_log ?? []).reduce((sum, entry) => sum + (entry.minutes ?? 0), 0));
const empty = computed(
    () =>
        !!props.context &&
        !props.context.completed.length &&
        !props.context.work_log.length &&
        !props.context.blockers.length &&
        !props.context.still_open.length,
);
const openBlockers = computed(() => (props.context?.blockers ?? []).filter((blocker) => !blocker.resolved).length);
const stats = computed<{ label: string; value: string; icon: IconName; tone: string }[]>(() => {
    const c = props.context;
    return [
        { label: 'Completed', value: String(c?.completed.length ?? 0), icon: 'check-circle', tone: 'text-success-fg' },
        {
            label: 'Time logged',
            value: minutes.value ? formatMinutes(minutes.value) : '0m',
            icon: 'clock',
            tone: 'text-info-fg',
        },
        { label: 'Open blockers', value: String(openBlockers.value), icon: 'alert-circle', tone: 'text-danger-fg' },
        {
            label: props.isToday ? 'Still open' : 'Left open',
            value: String(c?.still_open.length ?? 0),
            icon: 'circle-dashed',
            tone: 'text-fg-muted',
        },
    ];
});
const count = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
</script>

<template>
    <section aria-labelledby="context-heading" class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <Heading id="context-heading" :level="2" role="title">From your day</Heading>
                <Text role="label" tone="muted">
                    Draft a stand-up from this list: it adds Done, Learned, Blockers and Next to the entry.
                </Text>
            </div>
            <Button icon-start="edit" size="sm" :disabled="!context || empty" @click="$emit('draft')"
                >Draft stand-up</Button
            >
        </div>

        <div v-if="loading && !context" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-hidden="true">
            <Skeleton v-for="n in 4" :key="n" class="h-24" />
        </div>

        <EmptyState
            v-else-if="empty"
            icon="history"
            :title="isToday ? 'Nothing recorded yet today' : 'Nothing recorded on this day'"
            message="Complete tasks or log work and it shows up here."
        />

        <template v-else-if="context">
            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card v-for="stat in stats" :key="stat.label" as="div" class="gap-2">
                    <dt class="flex items-center gap-2 text-label font-semibold text-fg-muted">
                        <Icon :name="stat.icon" :class="stat.tone" />
                        {{ stat.label }}
                    </dt>
                    <dd class="font-display text-title font-semibold tabular">{{ stat.value }}</dd>
                </Card>
            </dl>

            <div class="grid items-start gap-4 lg:grid-cols-2">
                <Card v-if="context.completed.length" as="section" aria-labelledby="context-completed">
                    <CardHeader
                        title="Completed"
                        title-id="context-completed"
                        icon="check-circle"
                        :level="3"
                        :subtitle="count(context.completed.length, 'task', 'tasks')"
                    />
                    <ul class="flex flex-col divide-y divide-border">
                        <li v-for="task in context.completed" :key="task.id" class="py-2 first:pt-0 last:pb-0">
                            <RouterLink
                                :to="{ name: 'task-detail', params: { id: task.id } }"
                                class="line-clamp-1 text-label font-semibold underline-offset-4 hover:underline"
                                >{{ task.title }}</RouterLink
                            >
                        </li>
                    </ul>
                </Card>

                <Card v-if="context.work_log.length" as="section" aria-labelledby="context-work">
                    <CardHeader
                        title="Work logged"
                        title-id="context-work"
                        icon="clock"
                        :level="3"
                        :subtitle="`${count(context.work_log.length, 'entry', 'entries')}${minutes ? ` · ${formatMinutes(minutes)}` : ''}`"
                    />
                    <ul class="flex flex-col divide-y divide-border">
                        <li
                            v-for="entry in context.work_log"
                            :key="entry.id"
                            class="flex flex-col gap-1 py-3 text-label first:pt-0 last:pb-0"
                        >
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate font-semibold">{{ entry.task?.title }}</span>
                                <span v-if="entry.minutes" class="shrink-0 text-caption text-fg-muted tabular">{{
                                    formatMinutes(entry.minutes)
                                }}</span>
                            </span>
                            <span class="line-clamp-2 text-fg-muted">{{ entry.body }}</span>
                        </li>
                    </ul>
                </Card>

                <Card v-if="context.blockers.length" as="section" aria-labelledby="context-blockers">
                    <CardHeader
                        title="Blockers"
                        title-id="context-blockers"
                        icon="alert-circle"
                        :level="3"
                        :subtitle="`${openBlockers} open · ${context.blockers.length - openBlockers} resolved`"
                    />
                    <ul class="flex flex-col divide-y divide-border">
                        <li
                            v-for="blocker in context.blockers"
                            :key="blocker.id"
                            class="flex items-start gap-3 py-2 text-label first:pt-0 last:pb-0"
                        >
                            <Icon
                                :name="blocker.resolved ? 'check-circle' : 'alert-circle'"
                                :class="blocker.resolved ? 'text-fg-subtle' : 'text-danger-fg'"
                            />
                            <span :class="blocker.resolved && 'text-fg-muted line-through'"
                                >{{ blocker.description
                                }}<span class="sr-only">{{ blocker.resolved ? ' (resolved)' : '' }}</span></span
                            >
                        </li>
                    </ul>
                </Card>

                <Card v-if="context.still_open.length" as="section" aria-labelledby="context-open">
                    <CardHeader
                        :title="isToday ? 'Due today, still open' : 'Due that day, left open'"
                        title-id="context-open"
                        icon="circle-dashed"
                        :level="3"
                        :subtitle="count(context.still_open.length, 'task', 'tasks')"
                    />
                    <ul class="flex flex-col divide-y divide-border">
                        <li v-for="task in context.still_open" :key="task.id" class="py-2 first:pt-0 last:pb-0">
                            <RouterLink
                                :to="{ name: 'task-detail', params: { id: task.id } }"
                                class="line-clamp-1 text-label underline-offset-4 hover:underline"
                                >{{ task.title }}</RouterLink
                            >
                        </li>
                    </ul>
                </Card>
            </div>
        </template>
    </section>
</template>

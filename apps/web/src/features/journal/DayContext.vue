<script setup lang="ts">
import type { JournalContext } from '@focus/types';
import { Button, Card, CardHeader, Skeleton, Text } from '@focus/ui';
import { computed } from 'vue';
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
const subtitle = computed(() => {
    const c = props.context;
    if (!c) return props.isToday ? 'Today so far' : 'That day';
    const parts = [
        `${c.completed.length} done`,
        `${c.work_log.length} logged${minutes.value ? ` · ${formatMinutes(minutes.value)}` : ''}`,
    ];
    return parts.join(' · ');
});
</script>

<template>
    <Card as="section" aria-labelledby="context-heading" class="group gap-4">
        <CardHeader title="From your day" title-id="context-heading" icon="history" :subtitle="subtitle" />

        <Skeleton v-if="loading && !context" class="h-32" />
        <Text v-else-if="empty" role="label" tone="muted">
            Nothing recorded {{ isToday ? 'yet today' : 'on this day' }}. Complete tasks or log work and it shows up
            here.
        </Text>
        <dl v-else-if="context" class="flex flex-col gap-4 text-label">
            <div v-if="context.completed.length" class="flex flex-col gap-1">
                <dt class="text-caption font-semibold tracking-wide text-fg-muted uppercase">Completed</dt>
                <dd v-for="task in context.completed" :key="task.id">
                    <RouterLink
                        :to="{ name: 'task-detail', params: { id: task.id } }"
                        class="line-clamp-1 font-semibold underline-offset-4 hover:underline"
                        >{{ task.title }}</RouterLink
                    >
                </dd>
            </div>
            <div v-if="context.work_log.length" class="flex flex-col gap-2">
                <dt class="text-caption font-semibold tracking-wide text-fg-muted uppercase">Work logged</dt>
                <dd v-for="entry in context.work_log" :key="entry.id" class="flex flex-col gap-1">
                    <span class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 truncate font-semibold">{{ entry.task?.title }}</span>
                        <span v-if="entry.minutes" class="shrink-0 font-mono text-caption text-fg-muted">{{
                            formatMinutes(entry.minutes)
                        }}</span>
                    </span>
                    <span class="line-clamp-2 text-fg-muted">{{ entry.body }}</span>
                </dd>
            </div>
            <div v-if="context.blockers.length" class="flex flex-col gap-1">
                <dt class="text-caption font-semibold tracking-wide text-fg-muted uppercase">Blockers</dt>
                <dd v-for="blocker in context.blockers" :key="blocker.id" class="flex items-start gap-2">
                    <span
                        aria-hidden="true"
                        :class="[
                            'mt-2 size-1.5 shrink-0 rounded-full',
                            blocker.resolved ? 'bg-fg-subtle' : 'bg-danger',
                        ]"
                    />
                    <span :class="blocker.resolved && 'text-fg-muted line-through'"
                        >{{ blocker.description
                        }}<span class="sr-only">{{ blocker.resolved ? ' (resolved)' : '' }}</span></span
                    >
                </dd>
            </div>
            <div v-if="context.still_open.length" class="flex flex-col gap-1">
                <dt class="text-caption font-semibold tracking-wide text-fg-muted uppercase">
                    {{ isToday ? 'Due today, still open' : 'Due that day, left open' }}
                </dt>
                <dd v-for="task in context.still_open" :key="task.id">
                    <RouterLink
                        :to="{ name: 'task-detail', params: { id: task.id } }"
                        class="line-clamp-1 underline-offset-4 hover:underline"
                        >{{ task.title }}</RouterLink
                    >
                </dd>
            </div>
        </dl>

        <div class="flex flex-col gap-2 border-t border-border pt-4">
            <Button variant="outline" size="sm" icon-start="edit" class="self-start" @click="$emit('draft')"
                >Draft stand-up</Button
            >
            <Text role="caption" tone="muted">Adds Done, Learned, Blockers and Next to the entry from this list.</Text>
        </div>
    </Card>
</template>

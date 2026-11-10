<script setup lang="ts">
import { formatDate, formatMinutes } from '@/lib/dates';
import type { TaskFolderGroup } from './taskGroups';

const props = defineProps<{ group: TaskFolderGroup }>();

const percent = computed(() => (props.group.total ? Math.round((props.group.done / props.group.total) * 100) : 0));
const logged = computed(() => formatMinutes(props.group.loggedMinutes));
</script>

<template>
    <div class="flex flex-col gap-2">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-caption text-fg-muted">
            <span class="tabular">{{ group.done }}/{{ group.total }} done</span>
            <template v-if="logged">
                <span aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1 tabular"><Icon name="clock" />{{ logged }} logged</span>
            </template>
            <template v-if="group.nextDue">
                <span aria-hidden="true">·</span>
                <span class="tabular">Next {{ formatDate(group.nextDue) }}</span>
            </template>
            <Badge v-if="group.missed" tone="danger" icon="warning">{{ group.missed }} missed</Badge>
            <Badge v-if="group.unlogged" tone="warning" icon="hourglass">{{ group.unlogged }} no time logged</Badge>
        </p>
        <ProgressBar
            :value="percent"
            :label="`${group.title}: ${group.done} of ${group.total} done`"
            :tone="group.missed ? 'warning' : 'accent'"
        />
    </div>
</template>

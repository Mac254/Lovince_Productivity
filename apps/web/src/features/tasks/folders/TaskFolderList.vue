<script setup lang="ts">
import type { Task } from '@focus/types';
import { groupByTitle } from './titleGroups';

/** Task rows where repeated titles fold into one folder each; one-off titles stay plain rows. */
const props = defineProps<{ tasks: Task[] }>();
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();
defineSlots<{ actions?: (props: { task: Task }) => unknown }>();

const groups = computed(() => groupByTitle(props.tasks));
</script>

<template>
    <template v-for="group in groups" :key="group.key">
        <TaskRow
            v-if="group.tasks.length === 1"
            :task="group.lead"
            @toggle="emit('toggle', $event)"
            @star="emit('star', $event)"
        >
            <template v-if="$slots.actions" #actions="slotProps">
                <slot name="actions" v-bind="slotProps" />
            </template>
        </TaskRow>
        <TaskFolder v-else :group="group" @toggle="emit('toggle', $event)" @star="emit('star', $event)">
            <template v-if="$slots.actions" #actions="slotProps">
                <slot name="actions" v-bind="slotProps" />
            </template>
        </TaskFolder>
    </template>
</template>

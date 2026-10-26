<script setup lang="ts">
import type { TaskStatus } from '@focus/types';
import { Badge, type BadgeTone, type IconName } from '@focus/ui';
import { taskStatusLabels } from '@/lib/labels';

defineProps<{ status: TaskStatus }>();

const icons: Record<TaskStatus, IconName> = {
    not_started: 'circle-dashed',
    in_progress: 'circle-dot',
    blocked: 'alert-circle',
    completed: 'check-circle',
    cancelled: 'forbidden',
};
const tones: Record<TaskStatus, BadgeTone> = {
    not_started: 'neutral',
    in_progress: 'info',
    blocked: 'danger',
    completed: 'success',
    cancelled: 'neutral',
};
</script>

<template>
    <Badge :variant="status === 'cancelled' ? 'outline' : 'subtle'" :tone="tones[status]" :icon="icons[status]">{{
        taskStatusLabels[status]
    }}</Badge>
</template>

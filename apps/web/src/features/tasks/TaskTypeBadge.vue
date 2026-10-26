<script setup lang="ts">
import type { Task } from '@focus/types';
import { Badge } from '@focus/ui';
import ToneDot from '@/components/ToneDot.vue';
import { taskCategoryLabels } from '@/lib/labels';

defineProps<{ task: Pick<Task, 'category' | 'type'> }>();
</script>

<template>
    <Badge v-if="task.type"><ToneDot :tone="task.type.tone" />{{ task.type.name }}</Badge>
    <Badge v-else :tone="task.category === 'integration' ? 'accent' : 'neutral'">{{
        taskCategoryLabels[task.category]
    }}</Badge>
</template>

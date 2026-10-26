<script setup lang="ts">
import type { TaskPriority } from '@focus/types';
import { useSurfaceTone } from '@focus/ui';
import { computed } from 'vue';
import { taskPriorityLabels, taskPriorityRank } from '@/lib/labels';

/** Priority as a four-dot meter plus its label — never colour alone. */
const props = defineProps<{ priority: TaskPriority }>();
const surface = useSurfaceTone();

const filled = computed(() => {
    if (surface === 'highlight') return 'bg-highlight-ink';
    return props.priority === 'urgent' ? 'bg-fg' : 'bg-accent-fg';
});
const empty = computed(() => (surface === 'highlight' ? 'bg-highlight-hover' : 'bg-border'));
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-2 text-caption font-semibold',
            surface === 'highlight' ? 'text-highlight-ink' : 'text-fg-muted',
        ]"
    >
        <span
            aria-hidden="true"
            :class="[
                'inline-flex gap-1 rounded-full border px-2 py-1',
                surface === 'highlight' ? 'border-highlight-ink' : 'border-border-control',
            ]"
        >
            <span
                v-for="n in 4"
                :key="n"
                :class="['size-2 rounded-full', n <= taskPriorityRank[priority] ? filled : empty]"
            />
        </span>
        {{ taskPriorityLabels[priority] }}
    </span>
</template>

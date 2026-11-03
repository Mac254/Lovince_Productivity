<script setup lang="ts">
import type { Task } from '@focus/types';

const props = withDefaults(defineProps<{ task: Task; size?: 'md' | 'lg' }>(), { size: 'md' });
const emit = defineEmits<{ toggle: [task: Task] }>();

const done = computed(() => props.task.status === 'completed');
</script>

<template>
    <Pressable
        type="button"
        :aria-label="done ? `Mark “${task.title}” as not done` : `Mark “${task.title}” complete`"
        :aria-pressed="done"
        :class="[
            'group inline-flex shrink-0 items-center justify-center rounded-full border-2 transition duration-(--duration-fast) active:scale-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring',
            size === 'lg' ? 'size-(--control-sm)' : 'size-(--icon-lg)',
            done
                ? 'border-highlight bg-highlight text-highlight-ink'
                : 'border-border-control text-transparent hover:border-highlight-fg hover:text-highlight-fg',
        ]"
        @click="emit('toggle', task)"
    >
        <AnimatedIcon kind="check" :active="done" :size="size === 'lg' ? 'md' : 'sm'" />
    </Pressable>
</template>

<script setup lang="ts">
import type { Task } from '@focus/types';

const props = withDefaults(defineProps<{ task: Task; size?: 'md' | 'lg' }>(), { size: 'md' });
const emit = defineEmits<{ toggle: [task: Task] }>();

const done = computed(() => props.task.status === 'completed');
const previewing = ref(false);
const label = computed(() => (done.value ? `Mark “${props.task.title}” as not done` : `Mark “${props.task.title}” complete`));

function preview(event: PointerEvent, on: boolean): void {
    if (event.pointerType === 'mouse') previewing.value = on;
}
</script>

<template>
    <Tooltip :label="done ? 'Mark as not done' : 'Mark complete'">
        <Pressable
            type="button"
            :aria-label="label"
            :aria-pressed="done"
            :class="[
                'group inline-flex shrink-0 items-center justify-center rounded-full border-2 transition duration-(--duration-fast) active:scale-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring',
                size === 'lg' ? 'size-(--control-sm)' : 'size-(--icon-lg)',
                done
                    ? 'border-highlight bg-highlight text-highlight-ink'
                    : 'border-border-control text-fg-muted hover:border-highlight-fg hover:text-highlight-fg',
            ]"
            @pointerenter="preview($event, true)"
            @pointerleave="preview($event, false)"
            @click="
                previewing = false;
                emit('toggle', task);
            "
        >
            <AnimatedIcon kind="check" :active="done || previewing" :size="size === 'lg' ? 'md' : 'sm'" />
        </Pressable>
    </Tooltip>
</template>

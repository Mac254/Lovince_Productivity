<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Blocker } from '@focus/types';
import { Badge, DropdownMenu, Icon, Text, useToast, type MenuItem } from '@focus/ui';
import { relativeTime } from '@/lib/dates';

const props = defineProps<{ blockers: Blocker[]; showTask?: boolean }>();
const emit = defineEmits<{ changed: [] }>();
const toast = useToast();

async function setStatus(blocker: Blocker, status: Blocker['status']): Promise<void> {
    try {
        await api.blockers.update(blocker.id, { status });
        toast.success(status === 'resolved' ? 'Blocker resolved' : 'Blocker reopened');
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the blocker.'));
    }
}

async function remove(blocker: Blocker): Promise<void> {
    try {
        await api.blockers.remove(blocker.id);
        toast.success('Blocker deleted');
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the blocker.'));
    }
}

function menu(blocker: Blocker): MenuItem[] {
    return [
        blocker.status === 'open'
            ? { label: 'Mark resolved', icon: 'check-circle', onSelect: () => setStatus(blocker, 'resolved') }
            : { label: 'Reopen', icon: 'undo', onSelect: () => setStatus(blocker, 'open') },
        { label: 'Delete', icon: 'trash', destructive: true, onSelect: () => remove(blocker) },
    ];
}

defineExpose({ count: () => props.blockers.length });
</script>

<template>
    <ul class="flex flex-col divide-y divide-border">
        <li v-for="blocker in blockers" :key="blocker.id" class="flex items-start gap-3 py-3">
            <span
                :class="[
                    'mt-1 inline-flex size-(--control-sm) shrink-0 items-center justify-center rounded-full',
                    blocker.status === 'open' ? 'bg-danger text-danger-ink' : 'bg-surface-raised text-highlight-fg',
                ]"
            >
                <Icon :name="blocker.status === 'open' ? 'blocker' : 'check-circle'" />
            </span>
            <div class="min-w-0 flex-1">
                <Text
                    :class="[
                        'break-words whitespace-pre-line',
                        blocker.status === 'resolved' && 'text-fg-muted line-through',
                    ]"
                >
                    {{ blocker.description }}
                </Text>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-caption text-fg-muted">
                    <Badge :tone="blocker.status === 'open' ? 'neutral' : 'highlight'" variant="outline">
                        {{ blocker.status === 'open' ? 'Open' : 'Resolved' }}
                    </Badge>
                    <RouterLink
                        v-if="showTask && blocker.task"
                        :to="{ name: 'task-detail', params: { id: blocker.task.id } }"
                        class="truncate font-semibold text-fg underline-offset-4 hover:underline"
                    >
                        {{ blocker.task.title }}
                    </RouterLink>
                    <span>
                        {{
                            blocker.status === 'resolved' && blocker.resolved_at
                                ? `Resolved ${relativeTime(blocker.resolved_at)}`
                                : `Raised ${relativeTime(blocker.created_at)}`
                        }}
                    </span>
                </p>
            </div>
            <DropdownMenu :items="menu(blocker)" :label="`Actions for blocker`" size="sm" trigger-variant="ghost" />
        </li>
    </ul>
</template>

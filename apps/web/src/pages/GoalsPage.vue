<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Goal } from '@focus/types';
import { Button, ChipGroup, ConfirmDialog, EmptyState, ErrorState, PageHeader, Skeleton, useToast } from '@focus/ui';
import { computed, onMounted, ref } from 'vue';
import GoalCard from '@/features/goals/GoalCard.vue';
import GoalFormDialog from '@/features/goals/GoalFormDialog.vue';
import { useAsync } from '@/composables/useAsync';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';

type View = 'active' | 'weekly' | 'long_term' | 'completed' | 'all';

const store = useGoalsStore();
const dashboard = useDashboardStore();
const toast = useToast();
const { loading, error, run } = useAsync(() => store.load(true));

const view = ref<View>('active');
const viewOptions: { value: View; label: string }[] = [
    { value: 'active', label: 'Active' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'long_term', label: 'Long-term' },
    { value: 'completed', label: 'Completed' },
    { value: 'all', label: 'All' },
];

const visible = computed(() =>
    store.goals.filter((goal) => {
        if (view.value === 'active') return goal.status === 'active';
        if (view.value === 'completed') return goal.status === 'completed';
        if (view.value === 'all') return true;
        return goal.timeframe === view.value && goal.status !== 'cancelled';
    }),
);

const dialogOpen = ref(false);
const editing = ref<Goal | null>(null);
const deleting = ref<Goal | null>(null);
const deletingBusy = ref(false);

function open(goal: Goal | null): void {
    editing.value = goal;
    dialogOpen.value = true;
}

async function saved(): Promise<void> {
    void dashboard.load();
    await run();
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deletingBusy.value = true;
    try {
        await api.goals.remove(deleting.value.id);
        toast.success('Goal deleted');
        deleting.value = null;
        await saved();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the goal.'));
    } finally {
        deletingBusy.value = false;
    }
}

onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Goals"
            title-id="page-title"
            description="Weekly and long-term outcomes. Each goal’s tone colours its tasks."
        >
            <template #actions>
                <Button variant="secondary" icon-start="add" @click="open(null)">New goal</Button>
            </template>
        </PageHeader>

        <ChipGroup v-model="view" label="Filter goals" :options="viewOptions" />

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div
            v-else-if="loading && !store.loaded"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5"
            aria-hidden="true"
        >
            <Skeleton v-for="n in 4" :key="n" class="h-60" />
        </div>
        <div v-else-if="visible.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
            <GoalCard v-for="goal in visible" :key="goal.id" :goal="goal" @edit="open" @delete="deleting = $event" />
        </div>
        <EmptyState
            v-else
            icon="target"
            :title="store.goals.length ? 'No goals in this view' : 'No goals yet'"
            :message="
                store.goals.length
                    ? undefined
                    : 'Create a weekly or long-term goal, then link tasks to it to track progress.'
            "
        >
            <Button v-if="!store.goals.length" icon-start="add" @click="open(null)">Create a goal</Button>
        </EmptyState>

        <GoalFormDialog v-model:open="dialogOpen" :goal="editing" @saved="saved" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete goal?"
            :message="`“${deleting?.title}” will be deleted. Its tasks are kept and simply unlinked from the goal.`"
            :loading="deletingBusy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

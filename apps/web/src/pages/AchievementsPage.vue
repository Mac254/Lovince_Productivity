<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Achievement, AchievementCategory } from '@focus/types';
import type { AchievementOrder } from '@/features/achievements/months';
import { suggestionCategoryOptions } from '@/lib/labels';

const category = ref<AchievementCategory | ''>('');
const year = ref<string>('');
const ORDER_KEY = 'focus.achievements.order';
const order = ref<AchievementOrder>(readOrder());
const orderOptions: { value: AchievementOrder; label: string }[] = [
    { value: 'oldest', label: 'Oldest first' },
    { value: 'newest', label: 'Newest first' },
];
const dialogOpen = ref(false);
const editing = ref<Achievement | null>(null);

const { data, loading, error, run } = useAsync((signal) =>
    api.achievements.list(
        { category: category.value || undefined, year: year.value ? Number(year.value) : undefined },
        { signal },
    ),
);

const thisYear = new Date().getFullYear();
const yearOptions = Array.from({ length: 5 }, (_, i) => String(thisYear - i)).map((value) => ({ value, label: value }));
const filtered = computed(() => !!category.value || !!year.value);

function record(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function edit(achievement: Achievement): void {
    editing.value = achievement;
    dialogOpen.value = true;
}

function readOrder(): AchievementOrder {
    try {
        return localStorage.getItem(ORDER_KEY) === 'newest' ? 'newest' : 'oldest';
    } catch {
        return 'oldest';
    }
}

watch(order, (value) => {
    try {
        localStorage.setItem(ORDER_KEY, value);
    } catch {
        return;
    }
});
watch([category, year], run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Achievements"
            title-id="page-title"
            description="Wins, milestones and the things you’re proud of, month by month."
        >
            <template #actions>
                <Button icon-start="star" @click="record">Record achievement</Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <FacetFilter v-model="year" label="Year" :options="yearOptions" />
                <FacetFilter v-model="category" label="Category" :options="suggestionCategoryOptions" />
            </div>
            <SegmentedControl v-model="order" label="Order" size="sm" :options="orderOptions" />
        </div>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <Skeleton v-else-if="loading && !data" class="h-64" />
        <AchievementTimeline
            v-else-if="data && data.length"
            :achievements="data"
            :order="order"
            show-goal
            :class="loading && 'opacity-60'"
            @edit="edit"
            @changed="run"
        />
        <EmptyState
            v-else
            icon="star"
            :title="filtered ? 'No achievements here' : 'Your wins will live here'"
            message="Finished a book, shipped a feature, kept a streak? Record it so you can look back on it."
        >
            <Button variant="outline" icon-start="add" @click="record">Record achievement</Button>
        </EmptyState>

        <AchievementFormDialog v-model:open="dialogOpen" :achievement="editing" @saved="run" />
    </div>
</template>

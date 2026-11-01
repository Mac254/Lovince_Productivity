<script setup lang="ts">
import { api } from '@focus/api-client';
import type { BlockerStatus } from '@focus/types';
import { allFacet, withoutAll } from '@/lib/facets';

type View = BlockerStatus | 'all';

const dashboard = useDashboardStore();
const view = ref<View>('open');
const viewFacet = allFacet(view);
const dialogOpen = ref(false);
const views: { value: View; label: string }[] = [
    { value: 'open', label: 'Open' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'all', label: 'All' },
];

const { data, loading, error, run } = useAsync((signal) =>
    api.blockers.list(view.value === 'all' ? {} : { status: view.value }, { signal }),
);

function changed(): void {
    void dashboard.invalidate();
    void run();
}

watch(view, run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Blockers"
            title-id="page-title"
            description="What’s stopping progress, and when it was cleared."
        >
            <template #actions>
                <Button icon-start="blocker" @click="dialogOpen = true">Log blocker</Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <FacetFilter v-model="viewFacet" label="Status" :options="withoutAll(views)" />
        </div>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <Skeleton v-else-if="loading && !data" class="h-64" />
        <Card v-else-if="data && data.length" as="section" aria-label="Blockers" :class="loading && 'opacity-60'">
            <BlockerList :blockers="data" show-task @changed="changed" />
        </Card>
        <EmptyState
            v-else
            icon="blocker"
            :title="view === 'open' ? 'Nothing is blocking you' : 'No blockers here'"
            message="Log a blocker when something outside your control holds a task up."
        >
            <Button variant="outline" icon-start="add" @click="dialogOpen = true">Log blocker</Button>
        </EmptyState>

        <BlockerFormDialog v-model:open="dialogOpen" @saved="changed" />
    </div>
</template>

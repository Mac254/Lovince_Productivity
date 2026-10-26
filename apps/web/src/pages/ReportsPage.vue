<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import {
    Button,
    Card,
    EmptyState,
    ErrorState,
    Icon,
    PageHeader,
    Pagination,
    ProgressBar,
    Skeleton,
    Text,
    useToast,
} from '@focus/ui';
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAsync } from '@/composables/useAsync';
import { formatDate, todayIso } from '@/lib/dates';

const router = useRouter();
const toast = useToast();
const page = ref(1);
const generating = ref(false);

const { data, loading, error, run } = useAsync((signal) => api.reports.list(page.value, { signal }));

async function generate(): Promise<void> {
    generating.value = true;
    try {
        const report = await api.reports.generateToday();
        toast.success('Today’s report is ready');
        await router.push({ name: 'report-detail', params: { date: report.date } });
    } catch (e) {
        toast.error(errorMessage(e, 'Could not generate the report.'));
    } finally {
        generating.value = false;
    }
}

watch(page, run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Reports"
            title-id="page-title"
            description="A snapshot of every day, saved automatically at 23:55. Generate today’s any time to see it so far."
        >
            <template #actions>
                <Button icon-start="report" :loading="generating" @click="generate">Generate today’s report</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div
            v-else-if="loading && !data"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            aria-busy="true"
        >
            <Skeleton v-for="n in 6" :key="n" class="h-44" />
        </div>
        <template v-else-if="data && data.data.length">
            <ul
                :class="[
                    'grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 transition-opacity',
                    loading && 'opacity-60',
                ]"
            >
                <li v-for="report in data.data" :key="report.date">
                    <RouterLink
                        :to="{ name: 'report-detail', params: { date: report.date } }"
                        class="group block h-full rounded-lg"
                    >
                        <Card
                            as="article"
                            interactive
                            class="h-full gap-3"
                            :tone="report.date === todayIso() ? 'highlight' : 'default'"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-display text-title font-semibold">
                                    {{ formatDate(report.date, { weekday: 'long', day: 'numeric', month: 'short' }) }}
                                </p>
                                <Icon name="arrow-right" motion="nudge" />
                            </div>
                            <Text role="label" tone="muted" class="line-clamp-2">{{ report.narrative }}</Text>
                            <dl class="grid grid-cols-3 gap-2">
                                <div>
                                    <dt class="text-caption text-fg-muted">Completed</dt>
                                    <dd class="font-display text-heading font-semibold tabular">
                                        {{ report.summary.completed }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-caption text-fg-muted">Overdue</dt>
                                    <dd class="font-display text-heading font-semibold tabular">
                                        {{ report.summary.overdue }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-caption text-fg-muted">Blockers</dt>
                                    <dd class="font-display text-heading font-semibold tabular">
                                        {{ report.summary.open_blockers }}
                                    </dd>
                                </div>
                            </dl>
                            <div v-if="report.summary.completion_rate !== null" class="mt-auto flex flex-col gap-1">
                                <Text role="caption" tone="muted" tabular
                                    >{{ report.summary.due_completed }} of {{ report.summary.due }} due tasks done</Text
                                >
                                <ProgressBar
                                    :value="report.summary.completion_rate"
                                    :label="`Completion on ${report.date}`"
                                />
                            </div>
                        </Card>
                    </RouterLink>
                </li>
            </ul>
            <Pagination v-model:page="page" :last-page="data.meta.last_page" />
        </template>
        <EmptyState
            v-else
            icon="report"
            title="No reports yet"
            message="Reports are saved each night. Generate today’s to see one now."
        >
            <Button variant="outline" icon-start="report" :loading="generating" @click="generate"
                >Generate today’s report</Button
            >
        </EmptyState>
    </div>
</template>

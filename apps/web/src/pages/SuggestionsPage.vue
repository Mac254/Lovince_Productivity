<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import { SUGGESTION_CATEGORIES, SUGGESTION_STATUSES, type Suggestion, type SuggestionCategory } from '@focus/types';
import { suggestionCategoryLabels, suggestionStatusLabels } from '@/lib/labels';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const status = useTabQuery(SUGGESTION_STATUSES, 'new');
const category = ref<SuggestionCategory | 'all'>('all');
const suggestions = ref<Suggestion[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const formOpen = ref(false);
const editing = ref<Suggestion | null>(null);

const tabIcons = {
    new: 'lightbulb',
    accepted: 'calendar-check',
    done: 'check-circle',
    dismissed: 'close-circle',
} as const;
const tabs = computed<TabItem<typeof status.value>[]>(() =>
    SUGGESTION_STATUSES.map((value) => ({
        value,
        label: suggestionStatusLabels[value],
        icon: tabIcons[value],
        count: value === status.value ? suggestions.value.length : undefined,
    })),
);
const categories = computed(() => [
    { value: 'all' as const, label: 'All' },
    ...SUGGESTION_CATEGORIES.filter((value) => suggestions.value.some((s) => s.category === value)).map((value) => ({
        value,
        label: suggestionCategoryLabels[value],
    })),
]);
const visible = computed(() =>
    category.value === 'all' ? suggestions.value : suggestions.value.filter((s) => s.category === category.value),
);
const emptyCopy = computed(
    () =>
        ({
            new: 'You’re all caught up. Refresh for fresh ideas or add one of your own.',
            accepted: 'Suggestions you act on land here until you mark them done.',
            done: 'Finished suggestions show up here.',
            dismissed: 'Suggestions you skip are kept here in case you change your mind.',
        })[status.value],
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        suggestions.value = await api.suggestions.list(status.value);
        if (!categories.value.some((c) => c.value === category.value)) category.value = 'all';
    } catch (e) {
        error.value = errorMessage(e, 'Could not load your suggestions.');
    } finally {
        loading.value = false;
    }
}

async function refresh(): Promise<void> {
    refreshing.value = true;
    try {
        const added = await api.suggestions.refresh();
        toast.success(added ? `${added} new ${added === 1 ? 'suggestion' : 'suggestions'}` : 'Nothing new right now');
        if (status.value === 'new') await load();
        else status.value = 'new';
    } catch (e) {
        toast.error(errorMessage(e, 'Could not refresh suggestions.'));
    } finally {
        refreshing.value = false;
    }
}

function open(suggestion: Suggestion | null): void {
    editing.value = suggestion;
    formOpen.value = true;
}

watch(
    () => route.query.new,
    (value) => {
        if (!value) return;
        open(null);
        void router.replace({ query: { ...route.query, new: undefined } });
    },
    { immediate: true },
);
watch(status, load, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Suggestions"
            title-id="page-title"
            description="Ideas drawn from your tasks, goals, plans and journal, plus a library of small things that make a day better."
        >
            <template #actions>
                <Button variant="outline" icon-start="retry" :loading="refreshing" @click="refresh">Refresh</Button>
                <Button icon-start="add" @click="open(null)">New suggestion</Button>
            </template>
        </PageHeader>

        <Tabs v-model="status" label="Suggestion status" :tabs="tabs">
            <template v-for="tab in tabs" :key="tab.value" #[tab.value]>
                <ErrorState v-if="error" :message="error" @retry="load" />
                <div
                    v-else-if="loading && !suggestions.length"
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3"
                    aria-busy="true"
                >
                    <Skeleton v-for="n in 3" :key="n" class="h-56" />
                </div>
                <template v-else-if="suggestions.length">
                    <ChipGroup v-if="categories.length > 2" v-model="category" label="Category" :options="categories" />
                    <ul class="grid grid-cols-1 items-stretch gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <li v-for="suggestion in visible" :key="suggestion.id">
                            <SuggestionCard :suggestion="suggestion" @edit="open(suggestion)" @changed="load" />
                        </li>
                    </ul>
                </template>
                <EmptyState
                    v-else
                    icon="lightbulb"
                    :title="`No ${tab.label.toLowerCase()} suggestions`"
                    :message="emptyCopy"
                >
                    <Button
                        v-if="status === 'new'"
                        variant="outline"
                        icon-start="retry"
                        :loading="refreshing"
                        @click="refresh"
                        >Look for ideas</Button
                    >
                </EmptyState>
            </template>
        </Tabs>

        <SuggestionFormDialog v-model:open="formOpen" :suggestion="editing" @saved="load" />
    </div>
</template>

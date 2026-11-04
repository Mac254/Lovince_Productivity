<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import {
    SUGGESTION_STATUSES,
    type Suggestion,
    type SuggestionCategory,
    type SuggestionSource,
    type SuggestionStatus,
} from '@focus/types';
import { filterSuggestions, mergeSuggestions, type SuggestionSort } from '@/features/suggestions/catalogue';
import {
    suggestionCategoryOptions,
    suggestionSourceOptions,
    suggestionStatusIcons,
    suggestionStatusLabels,
} from '@/lib/labels';

const STATUS_TABS = [...SUGGESTION_STATUSES, 'all'] as const;
const FRESH_MS = 6000;

const route = useRoute();
const router = useRouter();
const toast = useToast();

const status = useTabQuery(STATUS_TABS, 'new');
const view = useViewMode('suggestions');
const category = ref<SuggestionCategory | ''>('');
const source = ref<SuggestionSource | ''>('');
const query = ref('');
const sort = ref<SuggestionSort>({ key: 'created', direction: 'desc' });

const suggestions = ref<Suggestion[]>([]);
const fresh = ref<number[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const formOpen = ref(false);
const editing = ref<Suggestion | null>(null);
let freshTimer: ReturnType<typeof setTimeout> | undefined;

const filtering = computed(() => Boolean(category.value || source.value || query.value.trim()));
const inStatus = computed(() =>
    filterSuggestions(suggestions.value, { status: status.value, category: '', source: '', query: '' }),
);
const visible = computed(() =>
    filterSuggestions(suggestions.value, {
        status: status.value,
        category: category.value,
        source: source.value,
        query: query.value,
    }),
);
const gridPage = usePagedList(visible, 12);

const tabs = computed<TabItem<(typeof STATUS_TABS)[number]>[]>(() =>
    STATUS_TABS.map((value) => ({
        value,
        label: value === 'all' ? 'All' : suggestionStatusLabels[value],
        icon: value === 'all' ? 'lightbulb' : suggestionStatusIcons[value],
        count: value === 'all' ? suggestions.value.length : suggestions.value.filter((s) => s.status === value).length,
    })),
);

const emptyCopy: Record<SuggestionStatus | 'all', string> = {
    new: 'You’re all caught up. Refresh for fresh ideas or add one of your own.',
    accepted: 'Suggestions you act on land here until you mark them done.',
    done: 'Finished suggestions show up here.',
    dismissed: 'Suggestions you skip are kept here in case you change your mind.',
    all: 'Refresh for ideas drawn from your day, or add one of your own.',
};

const countLabel = computed(() => {
    const shown = visible.value.length;
    const total = inStatus.value.length;
    const noun = total === 1 ? 'suggestion' : 'suggestions';
    return shown === total ? `${total} ${noun}` : `${shown} of ${total} ${noun}`;
});

function highlight(ids: number[]): void {
    if (!ids.length) return;
    fresh.value = ids;
    clearTimeout(freshTimer);
    freshTimer = setTimeout(() => (fresh.value = []), FRESH_MS);
}

async function load(): Promise<void> {
    error.value = '';
    try {
        const merged = mergeSuggestions(suggestions.value, await api.suggestions.list('all'));
        suggestions.value = merged.list;
        highlight(merged.added);
    } catch (e) {
        if (suggestions.value.length) toast.error(errorMessage(e, 'Could not update your suggestions.'));
        else error.value = errorMessage(e, 'Could not load your suggestions.');
    } finally {
        loading.value = false;
    }
}

async function refresh(): Promise<void> {
    refreshing.value = true;
    try {
        const added = await api.suggestions.refresh();
        await load();
        toast.success(added ? `${added} new ${added === 1 ? 'suggestion' : 'suggestions'}` : 'Nothing new right now');
        if (added && status.value !== 'new' && status.value !== 'all') status.value = 'new';
    } catch (e) {
        toast.error(errorMessage(e, 'Could not refresh suggestions.'));
    } finally {
        refreshing.value = false;
    }
}

async function topUp(previous: Suggestion): Promise<void> {
    if (previous.status !== 'new' || previous.source !== 'library') return;
    try {
        if (await api.suggestions.refresh()) await load();
    } catch {
        return;
    }
}

function replace(saved: Suggestion): void {
    const index = suggestions.value.findIndex((s) => s.id === saved.id);
    if (index === -1) {
        suggestions.value = [saved, ...suggestions.value];
        highlight([saved.id]);
    } else suggestions.value.splice(index, 1, saved);
}

function changed(saved: Suggestion, previous: Suggestion): void {
    replace(saved);
    void topUp(previous);
}

function removed(suggestion: Suggestion): void {
    suggestions.value = suggestions.value.filter((s) => s.id !== suggestion.id);
    void topUp(suggestion);
}

function open(suggestion: Suggestion | null): void {
    editing.value = suggestion;
    formOpen.value = true;
}

function clearFilters(): void {
    category.value = '';
    source.value = '';
    query.value = '';
}

watch([status, category, source, query], () => (gridPage.page = 1));
watch(
    () => route.query.new,
    (value) => {
        if (!value) return;
        open(null);
        void router.replace({ query: { ...route.query, new: undefined } });
    },
    { immediate: true },
);
onMounted(async () => {
    await api.suggestions.refresh(true).catch(() => 0);
    await load();
});
onBeforeUnmount(() => clearTimeout(freshTimer));
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
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <SearchField
                            id="suggestion-search"
                            v-model="query"
                            label="Search suggestions"
                            placeholder="Search suggestions"
                            class="lg:w-80"
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <FacetFilter v-model="category" label="Category" :options="suggestionCategoryOptions" />
                            <FacetFilter v-model="source" label="Source" :options="suggestionSourceOptions" />
                            <Button v-if="filtering" variant="ghost" size="sm" icon-start="close" @click="clearFilters"
                                >Clear filters</Button
                            >
                        </div>
                        <div class="flex items-center justify-between gap-4 lg:ml-auto">
                            <Text v-if="!loading && inStatus.length" role="caption" tone="muted" aria-live="polite">{{
                                countLabel
                            }}</Text>
                            <SegmentedControl
                                v-model="view"
                                label="Layout"
                                :options="viewModeOptions"
                                icon-only
                                size="sm"
                            />
                        </div>
                    </div>

                    <ErrorState v-if="error" :message="error" @retry="load" />
                    <div v-else-if="loading" aria-busy="true">
                        <div v-if="view === 'grid'" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <Skeleton v-for="n in 6" :key="n" class="h-80" />
                        </div>
                        <Card v-else class="flex flex-col gap-3">
                            <Skeleton v-for="n in 6" :key="n" class="h-10" />
                        </Card>
                    </div>
                    <EmptyState
                        v-else-if="!inStatus.length"
                        icon="lightbulb"
                        :title="
                            tab.value === 'all' ? 'No suggestions yet' : `No ${tab.label.toLowerCase()} suggestions`
                        "
                        :message="emptyCopy[tab.value]"
                    >
                        <Button
                            v-if="tab.value === 'new' || tab.value === 'all'"
                            variant="outline"
                            icon-start="retry"
                            :loading="refreshing"
                            @click="refresh"
                            >Look for ideas</Button
                        >
                    </EmptyState>
                    <EmptyState
                        v-else-if="!visible.length"
                        icon="search"
                        title="No matching suggestions"
                        message="Try another search or clear the filters to see everything here."
                    >
                        <Button variant="outline" icon-start="close" @click="clearFilters">Clear filters</Button>
                    </EmptyState>
                    <Transition v-else name="catalogue-view" mode="out-in">
                        <div v-if="view === 'grid'" key="grid" class="flex flex-col gap-6">
                            <TransitionGroup
                                tag="ul"
                                name="catalogue"
                                class="grid grid-cols-1 items-stretch gap-6 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <li v-for="suggestion in gridPage.visible" :key="suggestion.id">
                                    <SuggestionCard
                                        :suggestion="suggestion"
                                        :fresh="fresh.includes(suggestion.id)"
                                        @edit="open"
                                        @changed="changed"
                                        @removed="removed"
                                    />
                                </li>
                            </TransitionGroup>
                            <Pagination v-model:page="gridPage.page" :last-page="gridPage.lastPage" />
                        </div>
                        <SuggestionTable
                            v-else
                            key="table"
                            v-model:sort="sort"
                            :suggestions="visible"
                            :fresh="fresh"
                            @edit="open"
                            @changed="changed"
                            @removed="removed"
                        />
                    </Transition>
                </div>
            </template>
        </Tabs>

        <SuggestionFormDialog v-model:open="formOpen" :suggestion="editing" @saved="replace" />
    </div>
</template>

<style scoped>
.catalogue-move,
.catalogue-enter-active {
    transition:
        opacity var(--duration-slow) var(--ease-out),
        translate var(--duration-slow) var(--ease-out);
}

.catalogue-leave-active {
    transition:
        opacity var(--duration-fast) var(--ease-in),
        scale var(--duration-fast) var(--ease-in);
}

.catalogue-enter-from {
    opacity: 0;
    translate: 0 calc(var(--spacing) * 3);
}

.catalogue-leave-to {
    opacity: 0;
    scale: 0.96;
}

.catalogue-view-enter-active,
.catalogue-view-leave-active {
    transition: opacity var(--duration-fast) var(--ease-out);
}

.catalogue-view-enter-from,
.catalogue-view-leave-to {
    opacity: 0;
}
</style>

<script setup lang="ts">
import type { Suggestion } from '@focus/types';
import type { IconName } from '@focus/ui';
import { sortSuggestions, type SuggestionSort, type SuggestionSortKey } from './catalogue';

const props = defineProps<{ suggestions: Suggestion[]; fresh: number[] }>();
const sort = defineModel<SuggestionSort>('sort', { required: true });
const emit = defineEmits<{ edit: [Suggestion]; changed: [Suggestion, Suggestion]; removed: [Suggestion] }>();

const columns: { key: SuggestionSortKey; label: string }[] = [
    { key: 'title', label: 'Suggestion' },
    { key: 'category', label: 'Category' },
    { key: 'source', label: 'Source' },
    { key: 'status', label: 'Status' },
    { key: 'created', label: 'Added' },
];

const sorted = computed(() => sortSuggestions(props.suggestions, sort.value));
const paged = usePagedList(sorted, 15);

watch(
    () => [sort.value.key, sort.value.direction, props.suggestions.length],
    () => (paged.page = 1),
);

function toggle(key: SuggestionSortKey): void {
    sort.value =
        sort.value.key === key
            ? { key, direction: sort.value.direction === 'asc' ? 'desc' : 'asc' }
            : { key, direction: key === 'created' ? 'desc' : 'asc' };
}

function ariaSort(key: SuggestionSortKey): 'ascending' | 'descending' | 'none' {
    if (sort.value.key !== key) return 'none';
    return sort.value.direction === 'asc' ? 'ascending' : 'descending';
}

function sortIcon(key: SuggestionSortKey): IconName {
    if (sort.value.key !== key) return 'sort';
    return sort.value.direction === 'asc' ? 'chevron-up' : 'chevron-down';
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Card>
            <Table caption="Suggestions" label="Suggestions">
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            :aria-sort="ariaSort(column.key)"
                            :class="column.key === 'title' && 'w-full'"
                        >
                            <Pressable
                                :class="[
                                    'inline-flex items-center gap-1 rounded-sm whitespace-nowrap transition-colors duration-(--duration-instant) hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring',
                                    sort.key === column.key && 'text-fg',
                                ]"
                                @click="toggle(column.key)"
                            >
                                {{ column.label }}
                                <Icon :name="sortIcon(column.key)" size="sm" />
                            </Pressable>
                        </TableHead>
                        <TableHead align="end"><VisuallyHidden>Actions</VisuallyHidden></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <SuggestionRow
                        v-for="suggestion in paged.visible"
                        :key="suggestion.id"
                        :suggestion="suggestion"
                        :fresh="fresh.includes(suggestion.id)"
                        @edit="emit('edit', $event)"
                        @changed="(saved, previous) => emit('changed', saved, previous)"
                        @removed="emit('removed', $event)"
                    />
                </TableBody>
            </Table>
        </Card>
        <Pagination v-model:page="paged.page" :last-page="paged.lastPage" />
    </div>
</template>

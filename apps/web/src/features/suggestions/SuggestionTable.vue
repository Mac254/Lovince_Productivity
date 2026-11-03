<script setup lang="ts">
import type { Suggestion } from '@focus/types';
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
</script>

<template>
    <div class="flex flex-col gap-4">
        <Card>
            <Table caption="Suggestions" label="Suggestions">
                <TableHeader>
                    <TableRow>
                        <SortableHead
                            v-for="column in columns"
                            :key="column.key"
                            v-model:sort="sort"
                            :column="column.key"
                            :label="column.label"
                            :desc-first="column.key === 'created'"
                            :class="column.key === 'title' && 'w-full'"
                        />
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

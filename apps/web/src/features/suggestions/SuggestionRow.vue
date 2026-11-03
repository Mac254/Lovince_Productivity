<script setup lang="ts">
import type { Suggestion } from '@focus/types';
import { formatDate } from '@/lib/dates';
import {
    suggestionCategoryIcons,
    suggestionCategoryLabels,
    suggestionCategoryTones,
    suggestionSourceLabels,
    suggestionStatusLabels,
    suggestionStatusTones,
} from '@/lib/labels';
import { primaryAction, useSuggestionActions } from './useSuggestionActions';

const props = defineProps<{ suggestion: Suggestion; fresh?: boolean }>();
const emit = defineEmits<{ edit: [Suggestion]; changed: [Suggestion, Suggestion]; removed: [Suggestion] }>();

const { busy, deleting, accept, remove, menu } = useSuggestionActions(() => props.suggestion, {
    changed: (saved, previous) => emit('changed', saved, previous),
    removed: (suggestion) => emit('removed', suggestion),
    edit: (suggestion) => emit('edit', suggestion),
});

const primary = computed(() => primaryAction(props.suggestion));
const items = computed(() => menu(true));
</script>

<template>
    <TableRow :selected="fresh">
        <TableHead scope="row">
            <div class="flex max-w-md flex-col gap-1 py-1">
                <span class="font-semibold text-fg">{{ suggestion.title }}</span>
                <span v-if="suggestion.body" class="truncate text-caption font-normal text-fg-muted">{{
                    suggestion.body
                }}</span>
            </div>
        </TableHead>
        <TableCell>
            <Badge
                :tone="suggestionCategoryTones[suggestion.category]"
                :icon="suggestionCategoryIcons[suggestion.category]"
                >{{ suggestionCategoryLabels[suggestion.category] }}</Badge
            >
        </TableCell>
        <TableCell class="whitespace-nowrap text-fg-muted">{{ suggestionSourceLabels[suggestion.source] }}</TableCell>
        <TableCell>
            <Badge :tone="suggestionStatusTones[suggestion.status]" dot>{{
                suggestionStatusLabels[suggestion.status]
            }}</Badge>
        </TableCell>
        <TableCell numeric class="whitespace-nowrap text-fg-muted">{{ formatDate(suggestion.created_at) }}</TableCell>
        <TableCell align="end">
            <div class="flex items-center justify-end gap-2">
                <Button
                    v-if="suggestion.status === 'new'"
                    size="sm"
                    variant="outline"
                    :icon-start="primary.icon"
                    :loading="busy"
                    class="whitespace-nowrap"
                    @click="accept"
                    >{{ primary.label }}</Button
                >
                <DropdownMenu :items="items" :label="`${suggestion.title} actions`" trigger-variant="ghost" size="sm" />
            </div>
            <ConfirmDialog
                v-model:open="deleting"
                title="Delete suggestion?"
                :message="`“${suggestion.title}” will be removed. Ideas from the library can come back later.`"
                @confirm="remove"
            />
        </TableCell>
    </TableRow>
</template>

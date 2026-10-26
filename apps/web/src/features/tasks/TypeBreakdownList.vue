<script setup lang="ts">
import type { TypeBreakdownRow } from '@focus/types';
import { Text } from '@focus/ui';
import ToneDot from '@/components/ToneDot.vue';
import { taskCategoryLabels } from '@/lib/labels';

/** Per-type counts as a compact list; each row opens the Tasks page filtered to that type. */
defineProps<{ rows: TypeBreakdownRow[]; openLabel?: string; completedLabel?: string }>();

const label = (row: TypeBreakdownRow) => (row.category ? taskCategoryLabels[row.category] : row.label);
const query = (row: TypeBreakdownRow) =>
    row.type_id ? { task_type_id: row.type_id } : { category: row.category ?? undefined };
</script>

<template>
    <ul class="-mx-2 flex flex-col">
        <li v-for="row in rows" :key="row.key">
            <RouterLink
                :to="{ name: 'tasks', query: query(row) }"
                class="flex items-center gap-3 rounded-md px-2 py-2 transition-colors duration-(--duration-instant) hover:bg-surface-raised"
            >
                <ToneDot v-if="row.tone" :tone="row.tone" />
                <span v-else aria-hidden="true" class="inline-block size-2 shrink-0 rounded-full bg-fg-subtle" />
                <Text as="span" weight="semibold" truncate class="min-w-0 flex-1">{{ label(row) }}</Text>
                <Text v-if="openLabel" as="span" role="caption" tone="muted" tabular class="shrink-0">
                    {{ row.open }} {{ openLabel }}
                </Text>
                <Text v-if="completedLabel" as="span" role="caption" tone="muted" tabular class="shrink-0">
                    {{ row.completed }} {{ completedLabel }}
                </Text>
            </RouterLink>
        </li>
    </ul>
</template>

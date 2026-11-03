<script setup lang="ts" generic="K extends string">
import type { IconName, TableAlign } from '@focus/ui';

export interface TableSort<Key extends string> {
    key: Key;
    direction: 'asc' | 'desc';
}

const props = withDefaults(defineProps<{ column: K; label: string; descFirst?: boolean; align?: TableAlign }>(), {
    align: 'start',
});
const sort = defineModel<TableSort<K>>('sort', { required: true });

const active = computed(() => sort.value.key === props.column);
const ariaSort = computed(() => {
    if (!active.value) return 'none';
    return sort.value.direction === 'asc' ? 'ascending' : 'descending';
});
const icon = computed<IconName>(() => {
    if (!active.value) return 'sort';
    return sort.value.direction === 'asc' ? 'chevron-up' : 'chevron-down';
});

function toggle(): void {
    sort.value = active.value
        ? { key: props.column, direction: sort.value.direction === 'asc' ? 'desc' : 'asc' }
        : { key: props.column, direction: props.descFirst ? 'desc' : 'asc' };
}
</script>

<template>
    <TableHead :aria-sort="ariaSort" :align="align">
        <Pressable
            :class="[
                'inline-flex items-center gap-1 rounded-sm whitespace-nowrap transition-colors duration-(--duration-instant) hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring',
                active && 'text-fg',
            ]"
            @click="toggle"
        >
            {{ label }}
            <Icon :name="icon" size="sm" />
        </Pressable>
    </TableHead>
</template>

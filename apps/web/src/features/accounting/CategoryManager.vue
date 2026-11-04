<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type AccountingCategory, type CategoryKind } from '@focus/types';

const props = defineProps<{ categories: AccountingCategory[] }>();
const emit = defineEmits<{ changed: [] }>();

const toast = useToast();
const kind = ref<CategoryKind>('expense');
const kinds: { value: CategoryKind; label: string }[] = [
    { value: 'expense', label: 'Spending' },
    { value: 'income', label: 'Income' },
];
const name = ref('');
const error = ref('');
const busy = ref(false);

const visible = computed(() => props.categories.filter((c) => c.kind === kind.value));

async function add(): Promise<void> {
    error.value = '';
    if (!name.value.trim()) return;
    busy.value = true;
    try {
        await api.accounting.categories.create({ kind: kind.value, name: name.value.trim() });
        name.value = '';
        emit('changed');
    } catch (e) {
        if (isApiError(e) && e.isValidation) error.value = e.fieldErrors().name ?? errorMessage(e);
        else toast.error(errorMessage(e, 'Could not add the category.'));
    } finally {
        busy.value = false;
    }
}

async function toggleArchived(category: AccountingCategory): Promise<void> {
    try {
        await api.accounting.categories.update(category.id, { archived: !category.archived });
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the category.'));
    }
}
</script>

<template>
    <Card class="flex flex-col gap-4">
        <CardHeader title="Categories" title-id="categories-heading" icon="tag" class="mb-0" />
        <SegmentedControl v-model="kind" label="Category kind" :options="kinds" size="sm" />
        <ul class="flex flex-wrap gap-2">
            <li v-for="category in visible" :key="category.id">
                <Badge :tone="category.archived ? 'neutral' : 'accent'" :class="category.archived && 'line-through'">
                    {{ category.name }}
                </Badge>
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-label="`${category.archived ? 'Restore' : 'Archive'} ${category.name}`"
                    @click="toggleArchived(category)"
                    >{{ category.archived ? 'Restore' : 'Archive' }}</Button
                >
            </li>
        </ul>
        <form class="flex flex-wrap items-end gap-3" novalidate @submit.prevent="add">
            <Input
                id="category-name"
                v-model="name"
                :label="kind === 'expense' ? 'New spending category' : 'New income category'"
                :maxlength="LIMITS.accounting.categoryName"
                autocomplete="off"
                :error="error"
                class="min-w-56 flex-1"
            />
            <Button type="submit" variant="secondary" icon-start="add" :loading="busy">Add</Button>
        </form>
    </Card>
</template>

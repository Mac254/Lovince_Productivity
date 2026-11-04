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
const active = computed(() => visible.value.filter((c) => !c.archived));
const archived = computed(() => visible.value.filter((c) => c.archived));

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
        <ul v-if="active.length" class="grid gap-x-6 sm:grid-cols-2">
            <li
                v-for="category in active"
                :key="category.id"
                class="flex min-h-(--control-md) items-center justify-between gap-2 border-b border-border"
            >
                <span class="truncate text-body">{{ category.name }}</span>
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-label="`Archive ${category.name}`"
                    class="text-fg-muted"
                    @click="toggleArchived(category)"
                    >Archive</Button
                >
            </li>
        </ul>
        <p v-else class="text-label text-fg-muted">No categories yet. Add one below.</p>
        <section v-if="archived.length" aria-labelledby="archived-categories" class="flex flex-col gap-2">
            <h3 id="archived-categories" class="text-caption font-semibold text-fg-muted">Archived</h3>
            <ul class="flex flex-wrap gap-2">
                <li v-for="category in archived" :key="category.id">
                    <button
                        type="button"
                        :aria-label="`Restore ${category.name}`"
                        class="inline-flex h-(--control-sm) items-center gap-1 rounded-full border border-dashed border-border-control px-3 text-label text-fg-muted transition-colors duration-(--duration-instant) hover:border-border-strong hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring"
                        @click="toggleArchived(category)"
                    >
                        <Icon name="undo" />
                        {{ category.name }}
                    </button>
                </li>
            </ul>
        </section>
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

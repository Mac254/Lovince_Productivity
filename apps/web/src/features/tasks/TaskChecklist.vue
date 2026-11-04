<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type ChecklistItem } from '@focus/types';

const props = defineProps<{ taskId: number }>();
const emit = defineEmits<{ changed: [] }>();

const toast = useToast();
const outbox = useOutboxStore();
const uid = useId();
const items = ref<ChecklistItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const draft = ref('');
const draftError = ref<string>();
const adding = ref(false);
const editingId = ref<number | null>(null);
const editTitle = ref('');

const done = computed(() => items.value.filter((item) => item.done).length);
const full = computed(() => items.value.length >= LIMITS.checklist.max);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        items.value = await api.checklist.list(props.taskId);
    } catch (e) {
        error.value = errorMessage(e, 'Could not load the checklist.');
    } finally {
        loading.value = false;
    }
}

async function add(): Promise<void> {
    const title = draft.value.trim();
    if (!title || adding.value) return;
    adding.value = true;
    draftError.value = undefined;
    try {
        items.value = [...items.value, await api.checklist.create(props.taskId, title)];
        draft.value = '';
        emit('changed');
    } catch (e) {
        if (isOffline(e)) {
            const id = outbox.queueCreate('checklist', title, { title }, props.taskId);
            items.value = [
                ...items.value,
                { id, task_id: props.taskId, title, done: false, position: items.value.length },
            ];
            draft.value = '';
            toast.info('You’re offline. The item will sync when you’re back.');
        } else if (isApiError(e) && e.isValidation) draftError.value = e.fieldErrors().title;
        else toast.error(errorMessage(e, 'Could not add the item.'));
    } finally {
        adding.value = false;
    }
}

async function update(item: ChecklistItem, input: Partial<ChecklistItem>): Promise<void> {
    const previous = { ...item };
    Object.assign(item, input);
    if (isLocal(item)) return outbox.queueUpdate('checklist', item, input);
    try {
        Object.assign(item, await api.checklist.update(item.id, input));
        emit('changed');
    } catch (e) {
        if (isOffline(e)) return outbox.queueUpdate('checklist', previous, input);
        Object.assign(item, previous);
        toast.error(errorMessage(e, 'Could not update the item.'));
    }
}

function startEdit(item: ChecklistItem): void {
    editingId.value = item.id;
    editTitle.value = item.title;
}

async function saveEdit(item: ChecklistItem): Promise<void> {
    const title = editTitle.value.trim();
    editingId.value = null;
    if (title && title !== item.title) await update(item, { title });
}

async function move(index: number, offset: number): Promise<void> {
    const next = [...items.value];
    const [item] = next.splice(index, 1);
    next.splice(index + offset, 0, item!);
    const previous = items.value;
    items.value = next;
    try {
        items.value = await api.checklist.reorder(
            props.taskId,
            next.map((entry) => entry.id),
        );
    } catch (e) {
        items.value = previous;
        toast.error(errorMessage(e, 'Could not reorder the checklist.'));
    }
}

async function remove(item: ChecklistItem): Promise<void> {
    const previous = items.value;
    items.value = items.value.filter((entry) => entry.id !== item.id);
    if (isLocal(item)) return outbox.queueRemove('checklist', item);
    try {
        await api.checklist.remove(item.id);
        emit('changed');
    } catch (e) {
        if (isOffline(e)) return outbox.queueRemove('checklist', item);
        items.value = previous;
        toast.error(errorMessage(e, 'Could not remove the item.'));
    }
}

watch(() => props.taskId, load, { immediate: true });
</script>

<template>
    <Card as="section" aria-labelledby="checklist-heading" class="group gap-4">
        <CardHeader
            title="Checklist"
            title-id="checklist-heading"
            icon="checklist"
            :subtitle="items.length ? `${done} of ${items.length} done` : 'Break the task into steps'"
        />
        <ErrorState v-if="error" :message="error" @retry="load" />
        <Skeleton v-else-if="loading" class="h-24" />
        <template v-else>
            <ProgressBar
                v-if="items.length"
                :value="(done / items.length) * 100"
                label="Checklist progress"
                :tone="done === items.length ? 'highlight' : 'accent'"
            />
            <ul v-if="items.length" class="flex flex-col">
                <li
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="group/item flex min-h-(--control-md) items-center gap-2 rounded-md px-2 hover:bg-surface-raised"
                >
                    <form v-if="editingId === item.id" class="flex-1" @submit.prevent="saveEdit(item)">
                        <Input
                            :id="`${uid}-edit-${item.id}`"
                            v-model="editTitle"
                            label="Rename step"
                            :maxlength="LIMITS.checklist.title"
                            autofocus
                            @blur="saveEdit(item)"
                            @keydown.esc.prevent="editingId = null"
                        />
                    </form>
                    <Checkbox
                        v-else
                        :id="`${uid}-item-${item.id}`"
                        :model-value="item.done"
                        :label="item.title"
                        :class="['flex-1', item.done && 'text-fg-muted line-through']"
                        @update:model-value="update(item, { done: $event })"
                    />
                    <span
                        class="flex shrink-0 opacity-0 transition-opacity group-focus-within/item:opacity-100 group-hover/item:opacity-100"
                    >
                        <IconButton
                            icon="chevron-up"
                            label="Move up"
                            size="sm"
                            variant="ghost"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        />
                        <IconButton
                            icon="chevron-down"
                            label="Move down"
                            size="sm"
                            variant="ghost"
                            :disabled="index === items.length - 1"
                            @click="move(index, 1)"
                        />
                        <IconButton
                            icon="edit"
                            label="Rename item"
                            size="sm"
                            variant="ghost"
                            @click="startEdit(item)"
                        />
                        <IconButton icon="trash" label="Remove item" size="sm" variant="ghost" @click="remove(item)" />
                    </span>
                </li>
            </ul>
            <form v-if="!full" class="flex items-end gap-2" @submit.prevent="add">
                <Input
                    :id="`${uid}-new`"
                    v-model="draft"
                    label="Add a step"
                    placeholder="e.g. Draft the outline"
                    class="flex-1"
                    :maxlength="LIMITS.checklist.title"
                    :error="draftError"
                />
                <Button type="submit" variant="outline" icon-start="add" :loading="adding" :disabled="!draft.trim()"
                    >Add</Button
                >
            </form>
            <Text v-else role="caption" tone="muted">A checklist holds up to {{ LIMITS.checklist.max }} items.</Text>
        </template>
    </Card>
</template>

<script setup lang="ts">
import { errorMessage, isApiError } from '@focus/api-client';
import { GOAL_TONES, LIMITS, type GoalTone, type TaskType } from '@focus/types';
import { Button, ConfirmDialog, Dialog, IconButton, Input, Select, Text, useToast } from '@focus/ui';
import { reactive, ref, watch } from 'vue';
import InlineCreate from '@/components/InlineCreate.vue';
import ToneDot from '@/components/ToneDot.vue';
import { goalToneOptions } from '@/lib/labels';
import { useTaskTypesStore } from '@/stores/taskTypes';

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ changed: [] }>();

const store = useTaskTypesStore();
const toast = useToast();

const editing = ref<number | null>(null);
const draft = reactive({ name: '', tone: 'blue' as GoalTone });
const draftError = ref('');
const saving = ref(false);
const deleting = ref<TaskType | null>(null);
const deletingBusy = ref(false);

watch(open, (value) => {
    if (!value) return;
    editing.value = null;
    store.load(true).catch(() => toast.error('Could not load your task types.'));
});

function startEdit(type: TaskType): void {
    editing.value = type.id;
    Object.assign(draft, { name: type.name, tone: type.tone });
    draftError.value = '';
}

async function save(id: number): Promise<void> {
    saving.value = true;
    try {
        await store.update(id, { name: draft.name.trim(), tone: draft.tone });
        editing.value = null;
        emit('changed');
    } catch (e) {
        draftError.value =
            isApiError(e) && e.isValidation
                ? (Object.values(e.fieldErrors())[0] ?? '')
                : errorMessage(e, 'Could not save the type.');
    } finally {
        saving.value = false;
    }
}

async function create(name: string): Promise<number> {
    const type = await store.create({ name, tone: GOAL_TONES[store.types.length % GOAL_TONES.length] });
    emit('changed');
    return type.id;
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deletingBusy.value = true;
    try {
        await store.remove(deleting.value.id);
        toast.success('Type deleted');
        deleting.value = null;
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the type.'));
    } finally {
        deletingBusy.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Task types"
        description="Your own types sit alongside General, Development and Integration in every task form."
        size="sm"
    >
        <ul v-if="store.types.length" class="-mx-2 flex flex-col">
            <li v-for="type in store.types" :key="type.id" class="rounded-md px-2 py-3">
                <form
                    v-if="editing === type.id"
                    class="flex flex-col gap-3"
                    novalidate
                    @submit.prevent="save(type.id)"
                    @keydown.esc.stop.prevent="editing = null"
                >
                    <Input
                        :id="`type-name-${type.id}`"
                        v-model="draft.name"
                        label="Name"
                        :maxlength="LIMITS.taskType.name"
                        :error="draftError"
                        autocomplete="off"
                    />
                    <Select
                        :id="`type-tone-${type.id}`"
                        v-model="draft.tone"
                        label="Colour"
                        :options="goalToneOptions"
                    />
                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :loading="saving">Save</Button>
                        <Button size="sm" variant="ghost" @click="editing = null">Cancel</Button>
                    </div>
                </form>
                <div v-else class="flex items-center gap-3">
                    <ToneDot :tone="type.tone" />
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ type.name }}</span>
                    <Text role="caption" tone="muted" tabular>
                        {{ type.tasks_count ?? 0 }} {{ type.tasks_count === 1 ? 'task' : 'tasks' }}
                    </Text>
                    <IconButton
                        icon="edit"
                        :label="`Edit ${type.name}`"
                        size="sm"
                        variant="ghost"
                        @click="startEdit(type)"
                    />
                    <IconButton
                        icon="trash"
                        :label="`Delete ${type.name}`"
                        size="sm"
                        variant="ghost"
                        @click="deleting = type"
                    />
                </div>
            </li>
        </ul>
        <Text v-else role="label" tone="muted" class="pb-4">
            No types yet. Add one, e.g. Study, Personal or Review.
        </Text>
        <div class="border-t border-border pt-4">
            <InlineCreate id="types-new" noun="type" :maxlength="LIMITS.taskType.name" :create="create" />
        </div>
    </Dialog>

    <ConfirmDialog
        :open="!!deleting"
        title="Delete type?"
        :message="`“${deleting?.name}” will be removed. Its ${deleting?.tasks_count ?? 0} tasks stay and become General.`"
        :loading="deletingBusy"
        @update:open="!$event && (deleting = null)"
        @confirm="confirmDelete"
    />
</template>

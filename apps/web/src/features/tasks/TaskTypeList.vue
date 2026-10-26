<script setup lang="ts">
import { errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type GoalTone, type TaskType } from '@focus/types';
import { Button, ConfirmDialog, IconButton, Input, Select, Text, useToast } from '@focus/ui';
import { reactive, ref } from 'vue';
import ToneDot from '@/components/ToneDot.vue';
import { goalToneOptions } from '@/lib/labels';
import { useTaskTypesStore } from '@/stores/taskTypes';

/** The user's task types with their task counts; each can be renamed, recoloured or deleted. */
const emit = defineEmits<{ changed: [] }>();

const store = useTaskTypesStore();
const toast = useToast();

const editing = ref<number | null>(null);
const draft = reactive({ name: '', tone: 'blue' as GoalTone });
const draftError = ref('');
const saving = ref(false);
const deleting = ref<TaskType | null>(null);
const deletingBusy = ref(false);

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
    <ul class="-mx-2 flex flex-col divide-y divide-border">
        <li v-for="type in store.types" :key="type.id" class="px-2 py-3">
            <form
                v-if="editing === type.id"
                class="flex flex-col gap-3 sm:flex-row sm:items-start"
                novalidate
                @submit.prevent="save(type.id)"
                @keydown.esc.stop.prevent="editing = null"
            >
                <div class="min-w-0 flex-1">
                    <Input
                        :id="`type-name-${type.id}`"
                        v-model="draft.name"
                        label="Name"
                        :maxlength="LIMITS.taskType.name"
                        :error="draftError"
                        autocomplete="off"
                        autofocus
                    />
                </div>
                <div class="sm:w-40">
                    <Select
                        :id="`type-tone-${type.id}`"
                        v-model="draft.tone"
                        label="Colour"
                        :options="goalToneOptions"
                    />
                </div>
                <div class="flex gap-2 sm:self-end">
                    <Button type="submit" :loading="saving">Save</Button>
                    <Button variant="ghost" @click="editing = null">Cancel</Button>
                </div>
            </form>
            <div v-else class="flex items-center gap-3">
                <ToneDot :tone="type.tone" />
                <RouterLink
                    :to="{ name: 'tasks', query: { task_type_id: type.id, status: 'all' } }"
                    class="min-w-0 flex-1 truncate font-semibold hover:underline"
                >
                    {{ type.name }}
                </RouterLink>
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

    <ConfirmDialog
        :open="!!deleting"
        title="Delete type?"
        :message="`“${deleting?.name}” will be removed. Its ${deleting?.tasks_count ?? 0} tasks stay and become General.`"
        :loading="deletingBusy"
        @update:open="!$event && (deleting = null)"
        @confirm="confirmDelete"
    />
</template>

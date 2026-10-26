<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Task, type TaskCategory, type TaskInput, type TaskPriority, type TaskStatus } from '@focus/types';
import { Button, Checkbox, Dialog, Input, Select, Textarea, useToast } from '@focus/ui';
import { computed, reactive, ref, watch } from 'vue';
import { taskCategoryOptions, taskPriorityOptions, taskStatusOptions } from '@/lib/labels';
import { useGoalsStore } from '@/stores/goals';

const props = defineProps<{ task?: Task | null; defaults?: TaskInput }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [task: Task] }>();

const goals = useGoalsStore();
const toast = useToast();

const form = reactive({
    title: '',
    description: '' as string | null,
    status: 'not_started' as TaskStatus,
    priority: 'medium' as TaskPriority,
    category: 'general' as TaskCategory,
    start_date: '' as string | null,
    due_date: '' as string | null,
    goal_id: '' as number | '' | null,
    is_highlighted: false,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const isEdit = computed(() => !!props.task);
const goalOptions = computed(() => [
    { value: '' as const, label: 'No goal' },
    ...goals.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);

watch(open, (value) => {
    if (!value) return;
    const source: TaskInput = props.task ?? props.defaults ?? {};
    Object.assign(form, {
        title: source.title ?? '',
        description: source.description ?? '',
        status: source.status ?? 'not_started',
        priority: source.priority ?? 'medium',
        category: source.category ?? 'general',
        start_date: source.start_date ?? '',
        due_date: source.due_date ?? '',
        goal_id: source.goal_id ?? '',
        is_highlighted: source.is_highlighted ?? false,
    });
    errors.value = {};
    goals.load().catch(() => toast.error('Could not load goals for the goal picker.'));
});

function validate(): boolean {
    const next: Record<string, string> = {};
    if (!form.title.trim()) next.title = 'Give the task a title.';
    if (form.start_date && form.due_date && form.due_date < form.start_date) {
        next.due_date = 'The due date must be on or after the start date.';
    }
    errors.value = next;
    return Object.keys(next).length === 0;
}

async function submit(): Promise<void> {
    if (!validate()) return;
    saving.value = true;
    const payload: TaskInput = {
        title: form.title.trim(),
        description: form.description?.trim() || null,
        status: form.status,
        priority: form.priority,
        category: form.category,
        start_date: form.start_date || null,
        due_date: form.due_date || null,
        goal_id: form.goal_id === '' ? null : form.goal_id,
        is_highlighted: form.is_highlighted,
    };
    try {
        const saved = props.task ? await api.tasks.update(props.task.id, payload) : await api.tasks.create(payload);
        toast.success(isEdit.value ? 'Task updated' : 'Task created');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the task.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEdit ? 'Edit task' : 'New task'">
        <form id="task-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="task-title"
                v-model="form.title"
                label="Title"
                :maxlength="LIMITS.task.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <Textarea
                id="task-description"
                v-model="form.description"
                label="Description"
                optional
                :maxlength="LIMITS.task.description"
                :error="errors.description"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Select
                    id="task-status"
                    v-model="form.status"
                    label="Status"
                    :options="taskStatusOptions"
                    :error="errors.status"
                />
                <Select
                    id="task-priority"
                    v-model="form.priority"
                    label="Priority"
                    :options="taskPriorityOptions"
                    :error="errors.priority"
                />
                <Select
                    id="task-category"
                    v-model="form.category"
                    label="Type"
                    :options="taskCategoryOptions"
                    hint="Track integration separately from development."
                    :error="errors.category"
                />
                <Select
                    id="task-goal"
                    v-model="form.goal_id"
                    label="Goal"
                    :options="goalOptions"
                    optional
                    :error="errors.goal_id"
                />
                <Input
                    id="task-start"
                    v-model="form.start_date"
                    type="date"
                    label="Start date"
                    optional
                    :error="errors.start_date"
                />
                <Input
                    id="task-due"
                    v-model="form.due_date"
                    type="date"
                    label="Due date"
                    optional
                    :error="errors.due_date"
                />
            </div>
            <Checkbox id="task-highlight" v-model="form.is_highlighted" label="Highlight this task on the dashboard" />
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" form="task-form" :loading="saving">{{
                isEdit ? 'Save changes' : 'Create task'
            }}</Button>
        </template>
    </Dialog>
</template>

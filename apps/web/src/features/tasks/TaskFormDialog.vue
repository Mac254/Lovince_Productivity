<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import {
    GOAL_TONES,
    LIMITS,
    type GoalTimeframe,
    type Task,
    type TaskInput,
    type TaskPriority,
    type TaskStatus,
} from '@focus/types';
import { Button, Checkbox, Dialog, Input, Select, Textarea, useToast } from '@focus/ui';
import { computed, reactive, ref, useId, watch } from 'vue';
import InlineCreate from '@/components/InlineCreate.vue';
import { goalTimeframeOptions, taskPriorityOptions, taskStatusOptions } from '@/lib/labels';
import { fromKind, kindOf, kindOptions, type TaskKind } from '@/lib/taskKind';
import { useGoalsStore } from '@/stores/goals';
import { useTaskTypesStore } from '@/stores/taskTypes';

const props = defineProps<{ task?: Task | null; defaults?: TaskInput }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [task: Task] }>();

const goals = useGoalsStore();
/** Unique per instance: the top bar and pages each mount their own task form. */
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;
const taskTypes = useTaskTypesStore();
const toast = useToast();

const form = reactive({
    title: '',
    description: '' as string | null,
    status: 'not_started' as TaskStatus,
    priority: 'medium' as TaskPriority,
    kind: 'category:general' as TaskKind,
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
    ...goals.goals
        .filter((goal) => goal.status === 'active' || goal.status === 'on_hold' || goal.id === form.goal_id)
        .map((goal) => ({ value: goal.id, label: goal.title })),
]);
const typeOptions = computed(() => kindOptions(taskTypes.types));
const newGoalTimeframe = ref<GoalTimeframe>('weekly');
/** Bumped on every open so the inline "+ New …" forms start closed. */
const openCount = ref(0);

async function createType(name: string): Promise<number> {
    const tone = GOAL_TONES[taskTypes.types.length % GOAL_TONES.length];
    return (await taskTypes.create({ name, tone })).id;
}

async function createGoal(title: string): Promise<number> {
    const tone = GOAL_TONES[goals.goals.length % GOAL_TONES.length];
    const goal = await api.goals.create({ title, timeframe: newGoalTimeframe.value, tone });
    await goals.load(true);
    return goal.id;
}

watch(open, (value) => {
    if (!value) return;
    const source: TaskInput = props.task ?? props.defaults ?? {};
    Object.assign(form, {
        title: source.title ?? '',
        description: source.description ?? '',
        status: source.status ?? 'not_started',
        priority: source.priority ?? 'medium',
        kind: kindOf({ category: source.category ?? 'general', task_type_id: source.task_type_id ?? null }),
        start_date: source.start_date ?? '',
        due_date: source.due_date ?? '',
        goal_id: source.goal_id ?? '',
        is_highlighted: source.is_highlighted ?? false,
    });
    errors.value = {};
    openCount.value++;
    goals.load().catch(() => toast.error('Could not load goals for the goal picker.'));
    taskTypes.load().catch(() => toast.error('Could not load your task types.'));
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
        ...fromKind(form.kind),
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
        <form :id="fid('form')" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                :id="fid('title')"
                v-model="form.title"
                label="Title"
                :maxlength="LIMITS.task.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <Textarea
                :id="fid('description')"
                v-model="form.description"
                label="Description"
                optional
                :maxlength="LIMITS.task.description"
                :error="errors.description"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Select
                    :id="fid('status')"
                    v-model="form.status"
                    label="Status"
                    :options="taskStatusOptions"
                    :error="errors.status"
                />
                <Select
                    :id="fid('priority')"
                    v-model="form.priority"
                    label="Priority"
                    :options="taskPriorityOptions"
                    :error="errors.priority"
                />
                <div class="flex flex-col gap-2">
                    <Select
                        :id="fid('type')"
                        v-model="form.kind"
                        label="Type"
                        :options="typeOptions"
                        :error="errors.task_type_id ?? errors.category"
                    />
                    <InlineCreate
                        :id="fid('new-type')"
                        :key="`type-${openCount}`"
                        noun="type"
                        :maxlength="LIMITS.taskType.name"
                        :create="createType"
                        @created="form.kind = `type:${$event}`"
                    />
                </div>
                <div class="flex flex-col gap-2">
                    <Select
                        :id="fid('goal')"
                        v-model="form.goal_id"
                        label="Goal"
                        :options="goalOptions"
                        optional
                        :error="errors.goal_id"
                    />
                    <InlineCreate
                        :id="fid('new-goal')"
                        :key="`goal-${openCount}`"
                        noun="goal"
                        :maxlength="LIMITS.goal.title"
                        :create="createGoal"
                        @created="form.goal_id = $event"
                    >
                        <Select
                            :id="fid('new-goal-timeframe')"
                            v-model="newGoalTimeframe"
                            label="Timeframe"
                            :options="goalTimeframeOptions"
                        />
                    </InlineCreate>
                </div>
                <Input
                    :id="fid('start')"
                    v-model="form.start_date"
                    type="date"
                    label="Start date"
                    optional
                    :error="errors.start_date"
                />
                <Input
                    :id="fid('due')"
                    v-model="form.due_date"
                    type="date"
                    label="Due date"
                    optional
                    :error="errors.due_date"
                />
            </div>
            <Checkbox
                :id="fid('highlight')"
                v-model="form.is_highlighted"
                label="Highlight this task on the dashboard"
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                isEdit ? 'Save changes' : 'Create task'
            }}</Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import {
    GOAL_TONES,
    LIMITS,
    type GoalTimeframe,
    type Task,
    type TaskInput,
    type TaskPriority,
    type TaskRecurrence,
    type TaskStatus,
} from '@focus/types';
import { goalTimeframeOptions, taskPriorityOptions, taskRecurrenceOptions, taskStatusOptions } from '@/lib/labels';
import { parseDuration } from '@/lib/dates';
import { fromKind, kindOf, kindOptions, type TaskKind } from '@/lib/taskKind';
import type { CoverChoice } from '@/features/covers/CoverPicker.vue';

const props = defineProps<{ task?: Task | null; defaults?: TaskInput }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [task: Task] }>();

const goals = useGoalsStore();
/** Unique per instance: the top bar and pages each mount their own task form. */
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;
const taskTypes = useTaskTypesStore();
const toast = useToast();
const outbox = useOutboxStore();
const settings = useSettingsStore();

const form = reactive({
    title: '',
    description: '' as string | null,
    status: 'not_started' as TaskStatus,
    priority: 'medium' as TaskPriority,
    kind: 'category:general' as TaskKind,
    start_date: '' as string | null,
    due_date: '' as string | null,
    estimate: '',
    recurrence: '' as TaskRecurrence | '' | null,
    goal_id: '' as number | '' | null,
    is_highlighted: false,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const cover = ref<CoverChoice>(null);
const hasCover = ref(false);

watch(hasCover, (on) => {
    if (on && !cover.value) cover.value = { type: 'gallery', key: 'focus-1' };
});

const estimateMinutes = computed(() => parseDuration(form.estimate));
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
    const source: Omit<TaskInput, 'cover'> = props.task ?? props.defaults ?? {};
    Object.assign(form, {
        title: source.title ?? '',
        description: source.description ?? '',
        status: source.status ?? 'not_started',
        priority: source.priority ?? 'medium',
        kind: kindOf({ category: source.category ?? 'general', task_type_id: source.task_type_id ?? null }),
        start_date: source.start_date ?? '',
        due_date: source.due_date ?? '',
        estimate: source.estimate_minutes ? String(source.estimate_minutes) : '',
        recurrence: source.recurrence ?? '',
        goal_id: source.goal_id ?? '',
        is_highlighted: source.is_highlighted ?? false,
    });
    errors.value = {};
    cover.value = props.task?.cover ?? null;
    hasCover.value = !!cover.value;
    openCount.value++;
    goals.load().catch(() => toast.error('Could not load goals for the goal picker.'));
    taskTypes.load().catch(() => toast.error('Could not load your task types.'));
});

function validate(): boolean {
    const next: Record<string, string> = {};
    if (!form.title.trim()) next.title = 'Give the task a title.';
    if (form.estimate.trim() && estimateMinutes.value === null) {
        next.estimate_minutes = 'Use minutes or hours, like 45m, 1.5h or 2h 30m.';
    } else if ((estimateMinutes.value ?? 0) > LIMITS.task.estimateMinutes) {
        next.estimate_minutes = `Keep it under ${LIMITS.task.estimateMinutes / 60} hours.`;
    }
    if (form.start_date && form.due_date && form.due_date < form.start_date) {
        next.due_date = 'The due date must be on or after the start date.';
    }
    errors.value = next;
    return Object.keys(next).length === 0;
}

async function syncCover(task: Task, choice: CoverChoice): Promise<Task> {
    try {
        if (choice?.type === 'upload' && choice.file) return await api.tasks.uploadCover(task.id, choice.file);
        if (!choice && task.cover?.type === 'upload') return await api.tasks.removeCover(task.id);
    } catch (e) {
        toast.error(errorMessage(e, 'The task was saved, but its cover photo could not be updated.'));
    }
    return task;
}

function queue(payload: TaskInput, choice: CoverChoice): void {
    if (props.task) outbox.queueTaskUpdate(props.task, payload);
    else outbox.queueTaskCreate(payload);
    if (!choice && props.task?.cover?.type === 'upload') outbox.queueRemove('taskCovers', props.task);
    toast.info(
        choice?.type === 'upload' && choice.file
            ? `You’re offline. “${payload.title}” will sync when you’re back; add the photo again then.`
            : `You’re offline. “${payload.title}” will sync when you’re back.`,
    );
    open.value = false;
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
        recurrence: form.recurrence || null,
        ...(settings.values.tasks.estimates ? { estimate_minutes: estimateMinutes.value } : {}),
        goal_id: form.goal_id === '' ? null : form.goal_id,
        is_highlighted: form.is_highlighted,
    };
    const choice = hasCover.value ? cover.value : null;
    if (choice?.type === 'gallery') payload.cover = choice.key;
    else if (!choice && props.task?.cover?.type === 'gallery') payload.cover = null;
    try {
        if (props.task && isLocal(props.task)) return queue(payload, choice);
        const saved = props.task ? await api.tasks.update(props.task.id, payload) : await api.tasks.create(payload);
        toast.success(isEdit.value ? 'Task updated' : 'Task created');
        open.value = false;
        emit('saved', await syncCover(saved, choice));
    } catch (e) {
        if (isOffline(e)) queue(payload, choice);
        else if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the task.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEdit ? 'Edit task' : 'New task'" size="lg" :busy="saving">
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
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
                    <Combobox
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
                <DatePicker
                    :id="fid('start')"
                    v-model="form.start_date"
                    label="Start date"
                    optional
                    :error="errors.start_date"
                />
                <DatePicker
                    :id="fid('due')"
                    v-model="form.due_date"
                    label="Due date"
                    optional
                    :error="errors.due_date"
                />
                <Select
                    :id="fid('repeat')"
                    v-model="form.recurrence"
                    label="Repeat"
                    :options="taskRecurrenceOptions"
                    :hint="form.recurrence ? 'Finishing it schedules the next one' : undefined"
                    :error="errors.recurrence"
                />
                <Input
                    v-if="settings.values.tasks.estimates"
                    :id="fid('estimate')"
                    v-model="form.estimate"
                    label="Estimate"
                    placeholder="e.g. 90m or 2h"
                    hint="How long you expect it to take"
                    optional
                    :error="errors.estimate_minutes"
                />
            </div>
            <div class="flex flex-col gap-4 rounded-lg border border-border p-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="flex flex-col">
                        <Text :id="fid('cover-label')" as="span" role="label" weight="semibold">Cover photo</Text>
                        <Text :id="fid('cover-hint')" as="span" role="caption" tone="muted"
                            >Shows on the task card and its page.</Text
                        >
                    </span>
                    <Switch v-model="hasCover" :labelledby="fid('cover-label')" :describedby="fid('cover-hint')" />
                </div>
                <CoverPicker
                    v-if="hasCover"
                    v-model="cover"
                    label="Cover photo"
                    :error="errors.cover"
                    @invalid="toast.error($event)"
                />
            </div>
            <Checkbox
                :id="fid('highlight')"
                v-model="form.is_highlighted"
                label="Highlight this task on the dashboard"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                isEdit ? 'Save changes' : 'Create task'
            }}</Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Achievement, type AchievementCategory, type AchievementInput, type Task } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { suggestionCategoryOptions } from '@/lib/labels';

export interface AchievementDefaults {
    title?: string;
    goal_id?: number | null;
    task_id?: number | null;
}

const props = defineProps<{ achievement?: Achievement | null; defaults?: AchievementDefaults }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [achievement: Achievement] }>();

const toast = useToast();
const goalsStore = useGoalsStore();
const form = reactive({
    title: '',
    description: '' as string | null,
    achieved_on: '' as string | null,
    category: '' as AchievementCategory | '',
    goal_id: '' as number | '',
    task_id: '' as number | '',
});
const tasks = ref<Task[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.achievement);

const categoryOptions = [{ value: '' as const, label: 'No category' }, ...suggestionCategoryOptions];
const goalOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a goal' },
    ...goalsStore.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);
const taskOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a task' },
    ...tasks.value
        .filter((task) => !form.goal_id || task.goal_id === form.goal_id || task.id === form.task_id)
        .map((task) => ({ value: task.id, label: task.title })),
]);

watch(open, async (value) => {
    if (!value) return;
    const achievement = props.achievement;
    Object.assign(form, {
        title: achievement?.title ?? props.defaults?.title ?? '',
        description: achievement?.description ?? '',
        achieved_on: achievement?.achieved_on ?? todayIso(),
        category: achievement?.category ?? '',
        goal_id: achievement ? (achievement.goal_id ?? '') : (props.defaults?.goal_id ?? ''),
        task_id: achievement ? (achievement.task_id ?? '') : (props.defaults?.task_id ?? ''),
    });
    errors.value = {};
    const [, loaded] = await Promise.all([
        goalsStore.load().catch(() => []),
        api.tasks.list({ per_page: 100, sort: 'updated_at', direction: 'desc' }).catch(() => ({ data: [] as Task[] })),
    ]);
    tasks.value = loaded.data;
});

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) errors.value.title = 'Say what you achieved.';
    if (!form.achieved_on) errors.value.achieved_on = 'Pick the day it happened.';
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    const payload: AchievementInput = {
        title: form.title.trim(),
        description: form.description?.trim() || null,
        achieved_on: form.achieved_on!,
        category: form.category || null,
        goal_id: form.goal_id || null,
        task_id: form.task_id || null,
    };
    try {
        const saved = props.achievement
            ? await api.achievements.update(props.achievement.id, payload)
            : await api.achievements.create(payload);
        toast.success(isEdit.value ? 'Achievement updated' : 'Achievement recorded');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the achievement.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit achievement' : 'Record an achievement'"
        description="A win, a milestone or something you’re proud of. Link it to the goal it moved forward."
    >
        <form id="achievement-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="achievement-title"
                v-model="form.title"
                label="What did you achieve?"
                :maxlength="LIMITS.achievement.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <Textarea
                id="achievement-description"
                v-model="form.description"
                label="The story"
                optional
                :maxlength="LIMITS.achievement.description"
                :error="errors.description"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <DatePicker
                    id="achievement-date"
                    v-model="form.achieved_on"
                    label="Date achieved"
                    :error="errors.achieved_on"
                />
                <Select
                    id="achievement-category"
                    v-model="form.category"
                    label="Category"
                    optional
                    :options="categoryOptions"
                    :error="errors.category"
                />
                <Select
                    id="achievement-goal"
                    v-model="form.goal_id"
                    label="Goal"
                    optional
                    :options="goalOptions"
                    :error="errors.goal_id"
                />
                <Select
                    id="achievement-task"
                    v-model="form.task_id"
                    label="Task"
                    optional
                    :options="taskOptions"
                    :error="errors.task_id"
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" form="achievement-form" :loading="saving" icon-start="star">{{
                isEdit ? 'Save changes' : 'Record achievement'
            }}</Button>
        </template>
    </Dialog>
</template>

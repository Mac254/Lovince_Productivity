<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Blocker, type Task } from '@focus/types';
import { Button, Checkbox, Dialog, Select, Textarea, useToast } from '@focus/ui';
import { computed, ref, watch } from 'vue';

/** Log what is blocking progress, optionally against a task (and mark that task blocked). */
const props = defineProps<{ task?: Task | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [blocker: Blocker] }>();

const toast = useToast();
const description = ref('');
const taskId = ref<number | ''>('');
const markBlocked = ref(true);
const tasks = ref<Task[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const taskOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a task' },
    ...tasks.value.map((task) => ({ value: task.id, label: task.title })),
]);
const linkedTask = computed(() => (props.task ? props.task : tasks.value.find((t) => t.id === taskId.value)));

watch(open, async (value) => {
    if (!value) return;
    description.value = '';
    taskId.value = props.task?.id ?? '';
    markBlocked.value = true;
    errors.value = {};
    if (!props.task) {
        tasks.value = (
            await api.tasks.list({ status: 'open', per_page: 100 }).catch(() => ({ data: [] as Task[] }))
        ).data;
    }
});

async function submit(): Promise<void> {
    if (!description.value.trim()) {
        errors.value = { description: 'Describe what is blocking you.' };
        return;
    }
    saving.value = true;
    try {
        const blocker = await api.blockers.create({
            description: description.value.trim(),
            task_id: taskId.value || null,
        });
        const task = linkedTask.value;
        if (task && markBlocked.value && task.status !== 'blocked')
            await api.tasks.update(task.id, { status: 'blocked' });
        toast.success('Blocker logged');
        open.value = false;
        emit('saved', blocker);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not log the blocker.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Log a blocker"
        description="What’s stopping progress? You can resolve it once it’s cleared."
    >
        <form id="blocker-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Textarea
                id="blocker-description"
                v-model="description"
                label="What’s blocking you"
                :maxlength="LIMITS.blocker"
                :error="errors.description"
                autofocus
            />
            <Select
                v-if="!task"
                id="blocker-task"
                v-model="taskId"
                label="Task"
                optional
                :options="taskOptions"
                :error="errors.task_id"
            />
            <Checkbox
                v-if="linkedTask"
                id="blocker-mark"
                v-model="markBlocked"
                :label="`Mark “${linkedTask.title}” as blocked`"
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" form="blocker-form" :loading="saving" icon-start="blocker">Log blocker</Button>
        </template>
    </Dialog>
</template>

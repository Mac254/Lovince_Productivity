<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { Task } from '@focus/types';
import { formatDate } from '@/lib/dates';
import { completionRange, localDay, suggestedCompletionDay } from './completionDay';

/** Marks a task done on an earlier day, so work finished offline counts toward that day. */
const props = defineProps<{ task: Task | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [task: Task] }>();

const toast = useToast();
const uid = useId();
const day = ref('');
const error = ref<string>();
const saving = ref(false);
const range = computed(() => (props.task ? completionRange(props.task) : null));

watch(open, (value) => {
    if (!value || !props.task) return;
    day.value = props.task.completed_at ? localDay(props.task.completed_at) : suggestedCompletionDay(props.task);
    error.value = undefined;
});

async function submit(): Promise<void> {
    if (!props.task || !day.value) {
        error.value = 'Pick the day you finished it.';
        return;
    }
    saving.value = true;
    error.value = undefined;
    try {
        const saved = await api.tasks.update(props.task.id, { status: 'completed', completed_at: day.value });
        toast.success(`Completed “${props.task.title}” on ${formatDate(day.value, { dateStyle: 'medium' })}`);
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) error.value = e.fieldErrors().completed_at ?? e.message;
        else toast.error(errorMessage(e, 'Could not update the task.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" title="Completed on" :description="task?.title" size="sm">
        <form :id="`${uid}-form`" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <DatePicker
                :id="`${uid}-day`"
                v-model="day"
                label="Day you finished it"
                hint="Counts toward that day’s streak and report."
                :min="range?.min"
                :max="range?.max"
                :error="error"
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="`${uid}-form`" icon-start="check" :loading="saving">Mark complete</Button>
        </template>
    </Dialog>
</template>

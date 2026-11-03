<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Task, type TimeBlock } from '@focus/types';
import { formatMinutes } from '@/lib/dates';
import { clockTime, parseClockTime } from './timeline';

const props = defineProps<{
    date: string;
    block: TimeBlock | null;
    start: number;
    minutes: number;
    tasks: Task[];
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ changed: [] }>();

const toast = useToast();
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;

const form = reactive({
    task: '' as number | '' | null,
    title: '' as string | null,
    start: '',
    minutes: 60 as number | '' | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const removing = ref(false);

const durations = [15, 30, 45, 60, 90, 120, 180, 240].map((value) => ({ value, label: formatMinutes(value) }));
const taskOptions = computed(() => {
    const options = props.tasks.map((task) => ({ value: task.id, label: task.title }));
    const current = props.block?.task;
    if (current && !options.some((option) => option.value === current.id))
        options.unshift({ value: current.id, label: current.title });
    return [{ value: '' as const, label: 'No task' }, ...options];
});
const selectedTitle = computed(() => taskOptions.value.find((option) => option.value === form.task)?.label);

watch(open, (value) => {
    if (!value) return;
    Object.assign(form, {
        task: props.block?.task?.id ?? '',
        title: props.block && !props.block.task ? (props.block.title ?? '') : '',
        start: clockTime(props.block?.start_minute ?? props.start),
        minutes: props.block?.minutes ?? props.minutes,
    });
    if (!durations.some((option) => option.value === form.minutes))
        durations.push({ value: Number(form.minutes), label: formatMinutes(Number(form.minutes)) });
    errors.value = {};
});

async function submit(): Promise<void> {
    const start = parseClockTime(form.start);
    if (start === null) {
        errors.value = { start_minute: 'Enter a time like 09:30.' };
        return;
    }
    saving.value = true;
    errors.value = {};
    const input = {
        date: props.date,
        start_minute: start,
        minutes: Number(form.minutes),
        task_id: form.task === '' || form.task === null ? null : form.task,
        title: form.title?.trim() || null,
    };
    try {
        if (props.block) await api.timeBlocks.update(props.block.id, input);
        else await api.timeBlocks.create(input);
        open.value = false;
        emit('changed');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save this block.'));
    } finally {
        saving.value = false;
    }
}

async function remove(): Promise<void> {
    if (!props.block) return;
    removing.value = true;
    try {
        await api.timeBlocks.remove(props.block.id);
        open.value = false;
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not remove this block.'));
    } finally {
        removing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="block ? 'Edit block' : 'Block out time'" size="sm">
        <form :id="fid('form')" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Combobox :id="fid('task')" v-model="form.task" label="Task" :options="taskOptions" :error="errors.task_id" />
            <Input
                :id="fid('title')"
                v-model="form.title"
                label="Title"
                :optional="!!form.task"
                :placeholder="selectedTitle && form.task ? selectedTitle : 'e.g. Deep work, Lunch, Inbox'"
                :maxlength="LIMITS.timeBlock.title"
                :error="errors.title"
            />
            <div class="grid grid-cols-2 gap-4">
                <Input
                    :id="fid('start')"
                    v-model="form.start"
                    type="time"
                    label="Starts"
                    :error="errors.start_minute"
                />
                <Select
                    :id="fid('minutes')"
                    v-model="form.minutes"
                    label="Length"
                    :options="durations"
                    :error="errors.minutes"
                />
            </div>
        </form>
        <template #footer>
            <Button
                v-if="block"
                variant="ghost"
                icon-start="trash"
                class="sm:mr-auto"
                :loading="removing"
                @click="remove"
                >Remove</Button
            >
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                block ? 'Save changes' : 'Add block'
            }}</Button>
        </template>
    </Dialog>
</template>

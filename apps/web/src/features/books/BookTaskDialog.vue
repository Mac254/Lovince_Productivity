<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Book, type TaskRecurrence } from '@focus/types';
import { taskRecurrenceOptions } from '@/lib/labels';

const props = defineProps<{ book: Book | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();
const form = reactive({ title: '', due_date: null as string | null, recurrence: '' as TaskRecurrence | '' });
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(open, (value) => {
    if (!value || !props.book) return;
    Object.assign(form, { title: `Read ${props.book.title}`, due_date: null, recurrence: 'daily' });
    errors.value = {};
});

async function submit(): Promise<void> {
    if (!props.book) return;
    errors.value = {};
    if (!form.title.trim()) {
        errors.value.title = 'Give the task a title.';
        return;
    }
    saving.value = true;
    try {
        await api.books.addTask(props.book.id, {
            title: form.title.trim(),
            due_date: form.due_date,
            recurrence: form.recurrence || null,
        });
        toast.success('Reading task added');
        open.value = false;
        emit('saved');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not add the task.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Add a reading task"
        description="A task on your list for this book. It joins the book’s goal too."
        :busy="saving"
    >
        <form id="book-task-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="book-task-title"
                v-model="form.title"
                label="Task"
                :maxlength="LIMITS.task.title"
                required
                :error="errors.title"
                autofocus
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <DatePicker id="book-task-due" v-model="form.due_date" label="Due" optional :error="errors.due_date" />
                <Select
                    id="book-task-repeat"
                    v-model="form.recurrence"
                    label="Repeats"
                    :options="taskRecurrenceOptions"
                    :error="errors.recurrence"
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="book-task-form" :loading="saving" icon-start="add">Add task</Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Book, type BookInput, type BookStatus } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { bookStatusOptions } from '@/lib/labels';
import { toCount } from './progress';

const props = defineProps<{ book?: Book | null; status?: BookStatus }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [book: Book] }>();

const toast = useToast();
const goalsStore = useGoalsStore();
const form = reactive({
    title: '',
    author: '' as string | null,
    status: 'to_find' as BookStatus,
    chapters_total: '' as string | null,
    chapters_read: '' as string | null,
    started_on: null as string | null,
    finished_on: null as string | null,
    rating: '' as number | '',
    goal_id: '' as number | '',
    notes: '' as string | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.book);
const started = computed(() => form.status === 'reading' || form.status === 'finished');

const ratingOptions = [
    { value: '' as const, label: 'Not rated' },
    ...[5, 4, 3, 2, 1].map((value) => ({ value, label: `${value} of 5` })),
];
const goalOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a goal' },
    ...goalsStore.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);

watch(open, (value) => {
    if (!value) return;
    const book = props.book;
    Object.assign(form, {
        title: book?.title ?? '',
        author: book?.author ?? '',
        status: book?.status ?? props.status ?? 'to_find',
        chapters_total: book?.chapters_total ? String(book.chapters_total) : '',
        chapters_read: book?.chapters_read ? String(book.chapters_read) : '',
        started_on: book?.started_on ?? null,
        finished_on: book?.finished_on ?? null,
        rating: book?.rating ?? '',
        goal_id: book?.goal_id ?? '',
        notes: book?.notes ?? '',
    });
    errors.value = {};
    void goalsStore.load().catch(() => []);
});

watch(
    () => form.status,
    (status) => {
        if ((status === 'reading' || status === 'finished') && !form.started_on) form.started_on = todayIso();
        if (status === 'finished' && !form.finished_on) form.finished_on = todayIso();
    },
);

async function submit(): Promise<void> {
    errors.value = {};
    const total = toCount(form.chapters_total);
    const read = toCount(form.chapters_read);
    if (!form.title.trim()) errors.value.title = 'Give the book a title.';
    if (Number.isNaN(total) || (total !== null && (total < 1 || total > LIMITS.book.chapters)))
        errors.value.chapters_total = `Use a whole number from 1 to ${LIMITS.book.chapters}.`;
    if (Number.isNaN(read) || (read !== null && read > LIMITS.book.chapters))
        errors.value.chapters_read = 'Use a whole number of chapters.';
    else if (read !== null && total !== null && read > total)
        errors.value.chapters_read = 'That’s more chapters than the book has.';
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    const payload: BookInput = {
        title: form.title.trim(),
        author: form.author?.trim() || null,
        status: form.status,
        chapters_total: total,
        chapters_read: read ?? 0,
        started_on: started.value ? form.started_on : null,
        finished_on: form.status === 'finished' ? form.finished_on : null,
        rating: form.rating || null,
        goal_id: form.goal_id || null,
        notes: form.notes?.trim() || null,
    };
    try {
        const saved = props.book ? await api.books.update(props.book.id, payload) : await api.books.create(payload);
        toast.success(isEdit.value ? 'Book updated' : 'Book added');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the book.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit book' : 'Add a book'"
        description="A book you’ve read, are reading, have on the shelf or still need to find."
        :busy="saving"
    >
        <form id="book-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <div class="grid gap-6 sm:grid-cols-2">
                <Input
                    id="book-title"
                    v-model="form.title"
                    label="Title"
                    :maxlength="LIMITS.book.title"
                    required
                    autocomplete="off"
                    :error="errors.title"
                    autofocus
                />
                <Input
                    id="book-author"
                    v-model="form.author"
                    label="Author"
                    optional
                    :maxlength="LIMITS.book.author"
                    autocomplete="off"
                    :error="errors.author"
                />
                <Select id="book-status" v-model="form.status" label="Status" :options="bookStatusOptions" />
                <Combobox
                    id="book-goal"
                    v-model="form.goal_id"
                    label="Goal"
                    optional
                    :options="goalOptions"
                    :error="errors.goal_id"
                />
                <Input
                    id="book-chapters-total"
                    v-model="form.chapters_total"
                    label="Chapters in the book"
                    inputmode="numeric"
                    optional
                    hint="Lets progress show as a percentage"
                    :error="errors.chapters_total"
                />
                <Input
                    v-if="form.status !== 'to_find' && form.status !== 'finished'"
                    id="book-chapters-read"
                    v-model="form.chapters_read"
                    label="Chapters read so far"
                    inputmode="numeric"
                    optional
                    :error="errors.chapters_read"
                />
                <DatePicker
                    v-if="started"
                    id="book-started"
                    v-model="form.started_on"
                    label="Started"
                    :error="errors.started_on"
                />
                <DatePicker
                    v-if="form.status === 'finished'"
                    id="book-finished"
                    v-model="form.finished_on"
                    label="Finished"
                    :error="errors.finished_on"
                />
                <Select
                    v-if="form.status === 'finished'"
                    id="book-rating"
                    v-model="form.rating"
                    label="Rating"
                    optional
                    :options="ratingOptions"
                    :error="errors.rating"
                />
            </div>
            <Textarea
                id="book-notes"
                v-model="form.notes"
                label="Notes"
                optional
                hint="Why you want it, where to find it, or what stayed with you"
                :maxlength="LIMITS.book.notes"
                :error="errors.notes"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="book-form" :loading="saving" icon-start="book">{{
                isEdit ? 'Save changes' : 'Add book'
            }}</Button>
        </template>
    </Dialog>
</template>

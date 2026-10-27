<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type TaskNote } from '@focus/types';
import { Button, Dialog, Input, Textarea, useToast } from '@focus/ui';
import { computed, reactive, ref, useId, watch } from 'vue';
import { todayIso } from '@/lib/dates';

/** Logs work on a task, or edits an existing entry when `note` is given. */
const props = defineProps<{ taskId: number; taskTitle: string; note?: TaskNote | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [note: TaskNote] }>();

const toast = useToast();
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;

const form = reactive({ body: '', logged_on: todayIso(), minutes: '' as string | null });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.note);

watch(open, (value) => {
    if (!value) return;
    Object.assign(form, {
        body: props.note?.body ?? '',
        logged_on: props.note?.logged_on ?? todayIso(),
        minutes: props.note?.minutes?.toString() ?? '',
    });
    errors.value = {};
});

async function submit(): Promise<void> {
    if (!form.body.trim()) {
        errors.value = { body: 'Write what you did.' };
        return;
    }
    saving.value = true;
    errors.value = {};
    const input = {
        body: form.body.trim(),
        logged_on: form.logged_on || todayIso(),
        minutes: form.minutes === '' || form.minutes === null ? null : Number(form.minutes),
    };
    try {
        const saved = props.note
            ? await api.taskNotes.update(props.note.id, input)
            : await api.taskNotes.create(props.taskId, input);
        toast.success(isEdit.value ? 'Entry updated' : 'Work logged');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save this entry.'));
    } finally {
        saving.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        void submit();
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEdit ? 'Edit entry' : 'Log work'" :description="taskTitle" size="sm">
        <form :id="fid('form')" class="flex flex-col gap-6" novalidate @submit.prevent="submit" @keydown="onKeydown">
            <Textarea
                :id="fid('body')"
                v-model="form.body"
                label="What did you do?"
                :maxlength="LIMITS.taskNote.body"
                rows="5"
                :error="errors.body"
                placeholder="e.g. Read chapter 4 and noted the key ideas"
                autofocus
            />
            <div class="grid grid-cols-2 gap-4">
                <Input
                    :id="fid('day')"
                    v-model="form.logged_on"
                    type="date"
                    label="Day"
                    :max="todayIso()"
                    :error="errors.logged_on"
                />
                <Input
                    :id="fid('minutes')"
                    v-model="form.minutes"
                    type="number"
                    inputmode="numeric"
                    label="Minutes"
                    optional
                    :min="1"
                    :max="LIMITS.taskNote.minutes"
                    :error="errors.minutes"
                />
            </div>
        </form>
        <template #footer>
            <span class="mr-auto hidden self-center text-caption text-fg-muted sm:inline">Ctrl+Enter to save</span>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                isEdit ? 'Save changes' : 'Log work'
            }}</Button>
        </template>
    </Dialog>
</template>

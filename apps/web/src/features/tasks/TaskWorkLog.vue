<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type TaskNote } from '@focus/types';
import {
    Button,
    Card,
    CardHeader,
    ConfirmDialog,
    IconButton,
    Input,
    Skeleton,
    Text,
    Textarea,
    useToast,
} from '@focus/ui';
import { computed, reactive, ref, watch } from 'vue';
import { formatDate, formatMinutes, todayIso } from '@/lib/dates';

/** A task's work log: what was done, when, and how long it took. Newest day first. */
const props = defineProps<{ taskId: number }>();

const toast = useToast();
const notes = ref<TaskNote[]>([]);
const loading = ref(true);
const form = reactive({ body: '', logged_on: todayIso(), minutes: '' as string | null });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const editing = ref<number | null>(null);
const draft = reactive({ body: '', logged_on: '', minutes: '' as string | null });
const draftErrors = ref<Record<string, string>>({});
const deleting = ref<TaskNote | null>(null);
const deletingBusy = ref(false);

const totalMinutes = computed(() => notes.value.reduce((sum, n) => sum + (n.minutes ?? 0), 0));
const days = computed(() => {
    const groups = new Map<string, TaskNote[]>();
    for (const note of notes.value) groups.set(note.logged_on, [...(groups.get(note.logged_on) ?? []), note]);
    return [...groups].map(([date, items]) => ({
        date,
        items,
        minutes: items.reduce((sum, n) => sum + (n.minutes ?? 0), 0),
    }));
});
const subtitle = computed(() => {
    const count = `${notes.value.length} ${notes.value.length === 1 ? 'entry' : 'entries'}`;
    return totalMinutes.value ? `${count} · ${formatMinutes(totalMinutes.value)} logged` : count;
});

const toMinutes = (value: string | null) => (value === '' || value === null ? null : Number(value));

async function load(): Promise<void> {
    loading.value = true;
    try {
        notes.value = await api.taskNotes.list(props.taskId);
    } catch (e) {
        toast.error(errorMessage(e, 'Could not load the work log.'));
    } finally {
        loading.value = false;
    }
}

async function add(): Promise<void> {
    if (!form.body.trim()) {
        errors.value = { body: 'Write what you did.' };
        return;
    }
    saving.value = true;
    errors.value = {};
    try {
        const note = await api.taskNotes.create(props.taskId, {
            body: form.body.trim(),
            logged_on: form.logged_on || todayIso(),
            minutes: toMinutes(form.minutes),
        });
        notes.value = [note, ...notes.value].sort((a, b) => b.logged_on.localeCompare(a.logged_on) || b.id - a.id);
        Object.assign(form, { body: '', minutes: '', logged_on: todayIso() });
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not log this work.'));
    } finally {
        saving.value = false;
    }
}

function startEdit(note: TaskNote): void {
    editing.value = note.id;
    Object.assign(draft, { body: note.body, logged_on: note.logged_on, minutes: note.minutes?.toString() ?? '' });
    draftErrors.value = {};
}

async function saveEdit(id: number): Promise<void> {
    try {
        const updated = await api.taskNotes.update(id, {
            body: draft.body.trim(),
            logged_on: draft.logged_on,
            minutes: toMinutes(draft.minutes),
        });
        notes.value = notes.value
            .map((n) => (n.id === id ? updated : n))
            .sort((a, b) => b.logged_on.localeCompare(a.logged_on) || b.id - a.id);
        editing.value = null;
    } catch (e) {
        if (isApiError(e) && e.isValidation) draftErrors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the entry.'));
    }
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deletingBusy.value = true;
    try {
        await api.taskNotes.remove(deleting.value.id);
        notes.value = notes.value.filter((n) => n.id !== deleting.value?.id);
        deleting.value = null;
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the entry.'));
    } finally {
        deletingBusy.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        void add();
    }
}

watch(() => props.taskId, load, { immediate: true });
</script>

<template>
    <Card as="section" aria-labelledby="worklog-heading" class="group gap-4">
        <CardHeader title="Work log" title-id="worklog-heading" icon="history" :subtitle="subtitle" />

        <form class="flex flex-col gap-3" novalidate @submit.prevent="add" @keydown="onKeydown">
            <Textarea
                id="worklog-body"
                v-model="form.body"
                label="What did you do?"
                :maxlength="LIMITS.taskNote.body"
                rows="3"
                :error="errors.body"
                placeholder="e.g. Read chapter 4 and noted the key ideas"
            />
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:items-end">
                <Input
                    id="worklog-date"
                    v-model="form.logged_on"
                    type="date"
                    label="Day"
                    :max="todayIso()"
                    :error="errors.logged_on"
                />
                <Input
                    id="worklog-minutes"
                    v-model="form.minutes"
                    type="number"
                    inputmode="numeric"
                    label="Minutes"
                    optional
                    :min="1"
                    :max="LIMITS.taskNote.minutes"
                    :error="errors.minutes"
                />
                <Button type="submit" icon-start="add" :loading="saving" class="col-span-2 sm:col-span-1"
                    >Log work</Button
                >
            </div>
            <Text role="caption" tone="muted">Ctrl+Enter to log</Text>
        </form>

        <Skeleton v-if="loading" class="h-24" />
        <ol v-else-if="days.length" class="flex flex-col gap-4 border-t border-border pt-4">
            <li v-for="day in days" :key="day.date" class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="text-label font-semibold">
                        {{
                            day.date === todayIso()
                                ? 'Today'
                                : formatDate(day.date, { weekday: 'long', day: 'numeric', month: 'short' })
                        }}
                    </h3>
                    <Text v-if="day.minutes" as="span" role="caption" tone="muted" tabular>{{
                        formatMinutes(day.minutes)
                    }}</Text>
                </div>
                <ul class="flex flex-col gap-2 border-l-2 border-border pl-4">
                    <li v-for="note in day.items" :key="note.id">
                        <form
                            v-if="editing === note.id"
                            class="flex flex-col gap-3 rounded-md bg-surface-raised p-3"
                            novalidate
                            @submit.prevent="saveEdit(note.id)"
                            @keydown.esc.stop.prevent="editing = null"
                        >
                            <Textarea
                                :id="`worklog-edit-${note.id}`"
                                v-model="draft.body"
                                label="Entry"
                                :maxlength="LIMITS.taskNote.body"
                                rows="3"
                                :error="draftErrors.body"
                            />
                            <div class="grid grid-cols-2 gap-3">
                                <Input
                                    :id="`worklog-edit-date-${note.id}`"
                                    v-model="draft.logged_on"
                                    type="date"
                                    label="Day"
                                    :max="todayIso()"
                                    :error="draftErrors.logged_on"
                                />
                                <Input
                                    :id="`worklog-edit-minutes-${note.id}`"
                                    v-model="draft.minutes"
                                    type="number"
                                    label="Minutes"
                                    optional
                                    :min="1"
                                    :max="LIMITS.taskNote.minutes"
                                    :error="draftErrors.minutes"
                                />
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" size="sm">Save</Button>
                                <Button size="sm" variant="ghost" @click="editing = null">Cancel</Button>
                            </div>
                        </form>
                        <div v-else class="flex items-start gap-2">
                            <Text class="min-w-0 flex-1 break-words whitespace-pre-line">{{ note.body }}</Text>
                            <Text v-if="note.minutes" as="span" role="caption" tone="muted" tabular class="pt-1">{{
                                formatMinutes(note.minutes)
                            }}</Text>
                            <IconButton
                                icon="edit"
                                label="Edit entry"
                                size="sm"
                                variant="ghost"
                                @click="startEdit(note)"
                            />
                            <IconButton
                                icon="trash"
                                label="Delete entry"
                                size="sm"
                                variant="ghost"
                                @click="deleting = note"
                            />
                        </div>
                    </li>
                </ul>
            </li>
        </ol>
        <Text v-else role="label" tone="muted" class="border-t border-border pt-4">
            Nothing logged yet. Note what you did each time you work on this task.
        </Text>

        <ConfirmDialog
            :open="!!deleting"
            title="Delete entry?"
            message="This work log entry will be permanently deleted."
            :loading="deletingBusy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </Card>
</template>

<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TaskNote } from '@focus/types';
import { Button, Card, CardHeader, ConfirmDialog, IconButton, Skeleton, Text, useToast } from '@focus/ui';
import { computed, ref, watch } from 'vue';
import { formatDate, formatMinutes, todayIso } from '@/lib/dates';
import TaskNoteDialog from './TaskNoteDialog.vue';

/** A task's work log: what was done, when, and how long it took. Newest day first. */
const props = defineProps<{ taskId: number; taskTitle: string }>();

const toast = useToast();
const notes = ref<TaskNote[]>([]);
const loading = ref(true);
const dialogOpen = ref(false);
const editing = ref<TaskNote | null>(null);
const deleting = ref<TaskNote | null>(null);
const deletingBusy = ref(false);

const byNewest = (a: TaskNote, b: TaskNote) => b.logged_on.localeCompare(a.logged_on) || b.id - a.id;
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

function open(note: TaskNote | null): void {
    editing.value = note;
    dialogOpen.value = true;
}

function onSaved(note: TaskNote): void {
    notes.value = [...notes.value.filter((n) => n.id !== note.id), note].sort(byNewest);
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

watch(() => props.taskId, load, { immediate: true });
</script>

<template>
    <Card as="section" aria-labelledby="worklog-heading" class="group gap-4">
        <CardHeader title="Work log" title-id="worklog-heading" icon="history" :subtitle="subtitle">
            <Button size="sm" icon-start="add" @click="open(null)">Log work</Button>
        </CardHeader>

        <Skeleton v-if="loading" class="h-24" />
        <ol v-else-if="days.length" class="flex flex-col gap-6">
            <li v-for="day in days" :key="day.date" class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between gap-3 border-b border-border pb-2">
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
                <ul class="flex flex-col">
                    <li
                        v-for="note in day.items"
                        :key="note.id"
                        class="group/entry -mx-2 flex items-start gap-2 rounded-md px-2 py-2 transition-colors hover:bg-surface-raised"
                    >
                        <span aria-hidden="true" class="mt-2 size-2 shrink-0 rounded-full bg-highlight-fg" />
                        <Text class="min-w-0 flex-1 break-words whitespace-pre-line">{{ note.body }}</Text>
                        <Text v-if="note.minutes" as="span" role="caption" tone="muted" tabular class="pt-1">{{
                            formatMinutes(note.minutes)
                        }}</Text>
                        <IconButton icon="edit" label="Edit entry" size="sm" variant="ghost" @click="open(note)" />
                        <IconButton
                            icon="trash"
                            label="Delete entry"
                            size="sm"
                            variant="ghost"
                            @click="deleting = note"
                        />
                    </li>
                </ul>
            </li>
        </ol>
        <div v-else class="flex flex-col items-start gap-3 py-2">
            <Text role="label" tone="muted"
                >Nothing logged yet. Note what you did each time you work on this task.</Text
            >
            <Button variant="outline" size="sm" icon-start="add" @click="open(null)">Log your first entry</Button>
        </div>

        <TaskNoteDialog
            v-model:open="dialogOpen"
            :task-id="taskId"
            :task-title="taskTitle"
            :note="editing"
            @saved="onSaved"
        />
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

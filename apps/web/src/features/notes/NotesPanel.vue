<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS } from '@focus/types';
import JournalPreview from '@/features/journal/JournalPreview.vue';
import { appendNote } from './notes';

/** One running page of personal notes: read it at a glance, add a line fast, or edit the whole page. */
const toast = useToast();
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;

const notes = ref('');
const draft = ref('');
const quick = ref('');
const editing = ref(false);
const loading = ref(true);
const saving = ref(false);
const failed = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    failed.value = false;
    try {
        notes.value = (await api.profile.notes()).notes;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

async function save(text: string): Promise<boolean> {
    saving.value = true;
    try {
        notes.value = (await api.profile.saveNotes(text)).notes;
        return true;
    } catch (e) {
        toast.error(errorMessage(e, 'Could not save your notes.'));
        return false;
    } finally {
        saving.value = false;
    }
}

function startEditing(): void {
    draft.value = notes.value;
    editing.value = true;
}

async function finishEditing(): Promise<void> {
    if (draft.value === notes.value || (await save(draft.value))) editing.value = false;
}

async function addQuick(): Promise<void> {
    const line = quick.value.trim();
    if (!line || saving.value) return;
    if (await save(appendNote(notes.value, line))) quick.value = '';
}

onMounted(load);
// Closing the panel mid-edit keeps what was typed.
onBeforeUnmount(() => {
    if (editing.value && draft.value !== notes.value) void save(draft.value);
});
</script>

<template>
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading :level="2" role="title">Notes</Heading>
            <Button
                v-if="!loading && !failed"
                size="sm"
                :variant="editing ? 'primary' : 'outline'"
                :icon-start="editing ? 'check' : 'edit'"
                :loading="editing && saving"
                @click="editing ? finishEditing() : startEditing()"
                >{{ editing ? 'Done' : 'Edit' }}</Button
            >
        </div>

        <div v-if="loading" class="flex flex-col gap-2" aria-busy="true" aria-label="Loading your notes">
            <Skeleton shape="line" class="h-4 w-3/4" />
            <Skeleton shape="line" class="h-4 w-1/2" />
            <Skeleton shape="line" class="h-4 w-2/3" />
        </div>
        <Alert v-else-if="failed" tone="danger">
            <span class="flex flex-col items-start gap-3">
                Could not load your notes.
                <Button size="sm" variant="outline" icon-start="retry" @click="load">Try again</Button>
            </span>
        </Alert>
        <template v-else-if="editing">
            <Textarea
                :id="fid('page')"
                v-model="draft"
                label="Your notes"
                hint="Markdown works: ## headings, - lists, **bold**."
                rows="14"
                :maxlength="LIMITS.notes"
            />
        </template>
        <template v-else>
            <JournalPreview :source="notes">
                <template #empty>
                    <Text role="label" tone="muted"
                        >Nothing here yet. Jot what you want to keep in front of you, and it'll be one tap away.</Text
                    >
                </template>
            </JournalPreview>
            <form class="flex items-end gap-2 border-t border-border pt-4" @submit.prevent="addQuick">
                <div class="min-w-0 flex-1">
                    <Input
                        :id="fid('quick')"
                        v-model="quick"
                        label="Add a note"
                        placeholder="Type and press Enter"
                        :maxlength="LIMITS.notes"
                    />
                </div>
                <IconButton
                    type="submit"
                    icon="add"
                    label="Add the note"
                    variant="primary"
                    :disabled="saving || !quick.trim()"
                />
            </form>
        </template>
    </div>
</template>

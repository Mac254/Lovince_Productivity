<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Book } from '@focus/types';
import JournalPreview from '@/features/journal/JournalPreview.vue';
import { appendNote } from '@/features/notes/notes';

/** A podcast's running page of lessons, kept like the notes panel: add a line fast or edit the whole page. */
const props = defineProps<{ book: Book }>();
const emit = defineEmits<{ saved: [] }>();
const toast = useToast();
const uid = useId();

const page = ref(props.book.notes ?? '');
const draft = ref('');
const quick = ref('');
const editing = ref(false);
const saving = ref(false);
watch(
    () => props.book.notes,
    (notes) => {
        if (!editing.value) page.value = notes ?? '';
    },
);

async function save(text: string): Promise<boolean> {
    saving.value = true;
    try {
        page.value = (await api.books.update(props.book.id, { notes: text.trim() || null })).notes ?? '';
        emit('saved');
        return true;
    } catch (e) {
        toast.error(errorMessage(e, 'Could not save your lessons.'));
        return false;
    } finally {
        saving.value = false;
    }
}

function startEditing(): void {
    draft.value = page.value;
    editing.value = true;
}

async function finishEditing(): Promise<void> {
    if (draft.value === page.value || (await save(draft.value))) editing.value = false;
}

async function addQuick(): Promise<void> {
    const line = quick.value.trim();
    if (!line || saving.value) return;
    if (await save(appendNote(page.value, line))) quick.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex justify-end">
            <Button
                size="sm"
                :variant="editing ? 'primary' : 'outline'"
                :icon-start="editing ? 'check' : 'edit'"
                :loading="editing && saving"
                @click="editing ? finishEditing() : startEditing()"
                >{{ editing ? 'Done' : 'Edit page' }}</Button
            >
        </div>
        <Textarea
            v-if="editing"
            :id="`${uid}-page`"
            v-model="draft"
            label="Your lessons"
            hint="Markdown works: ## headings, - lists, **bold**."
            rows="12"
            :maxlength="LIMITS.book.notes"
        />
        <template v-else>
            <JournalPreview :source="page">
                <template #empty>
                    <Text role="label" tone="muted"
                        >Nothing yet. Write down what this show has taught you, one line at a time.</Text
                    >
                </template>
            </JournalPreview>
            <form class="flex items-end gap-2 border-t border-border pt-4" @submit.prevent="addQuick">
                <div class="min-w-0 flex-1">
                    <Input
                        :id="`${uid}-quick`"
                        v-model="quick"
                        label="Add a lesson"
                        placeholder="Type and press Enter"
                        :maxlength="LIMITS.book.notes"
                    />
                </div>
                <IconButton
                    type="submit"
                    icon="add"
                    label="Add the lesson"
                    variant="primary"
                    :disabled="saving || !quick.trim()"
                />
            </form>
        </template>
    </div>
</template>

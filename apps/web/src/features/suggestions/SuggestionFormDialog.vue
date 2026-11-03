<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Suggestion, type SuggestionCategory } from '@focus/types';
import { suggestionCategoryOptions } from '@/lib/labels';

const props = defineProps<{ suggestion: Suggestion | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [Suggestion] }>();

const toast = useToast();
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;

const title = ref('');
const body = ref<string | null>('');
const category = ref<SuggestionCategory | '' | null>('fun');
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(open, (value) => {
    if (!value) return;
    title.value = props.suggestion?.title ?? '';
    body.value = props.suggestion?.body ?? '';
    category.value = props.suggestion?.category ?? 'fun';
    errors.value = {};
});

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    const input = {
        title: title.value.trim(),
        body: body.value?.trim() || null,
        category: category.value || undefined,
    };
    try {
        const saved = props.suggestion
            ? await api.suggestions.update(props.suggestion.id, input)
            : await api.suggestions.create(input);
        toast.success(props.suggestion ? 'Suggestion updated' : 'Suggestion added');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the suggestion.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" size="sm" :title="suggestion ? 'Edit suggestion' : 'New suggestion'" :busy="saving">
        <form :id="fid('form')" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                :id="fid('title')"
                v-model="title"
                label="Idea"
                placeholder="e.g. Try a new recipe on Friday"
                :maxlength="LIMITS.suggestion.title"
                :error="errors.title"
            />
            <Select
                :id="fid('category')"
                v-model="category"
                label="Category"
                :options="suggestionCategoryOptions"
                :error="errors.category"
            />
            <Textarea
                :id="fid('body')"
                v-model="body"
                label="Why it helps"
                optional
                :maxlength="LIMITS.suggestion.body"
                :error="errors.body"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                suggestion ? 'Save changes' : 'Add suggestion'
            }}</Button>
        </template>
    </Dialog>
</template>

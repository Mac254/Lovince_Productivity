<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type MoneyRule } from '@focus/types';

/** Writes or edits a money rule and the reason behind it. */
const props = defineProps<{ rule?: MoneyRule | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();
const form = reactive({ title: '', reason: '', pinned: false });
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(open, (value) => {
    if (!value) return;
    form.title = props.rule?.title ?? '';
    form.reason = props.rule?.reason ?? '';
    form.pinned = props.rule?.pinned ?? false;
    errors.value = {};
});

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) {
        errors.value.title = 'Write the rule.';
        return;
    }
    saving.value = true;
    const input = { title: form.title.trim(), reason: form.reason.trim() || null, pinned: form.pinned };
    try {
        if (props.rule) await api.accounting.rules.update(props.rule.id, input);
        else await api.accounting.rules.create(input);
        toast.success(props.rule ? 'Rule updated' : 'Rule added');
        open.value = false;
        emit('saved');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the rule.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="rule ? 'Edit rule' : 'New rule'"
        description="A short rule you want to live by with money, and why it matters to you."
        :busy="saving"
    >
        <form id="rule-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="rule-title"
                v-model="form.title"
                label="Rule"
                placeholder="Pay my goals before I spend"
                required
                autofocus
                :maxlength="LIMITS.accounting.ruleTitle"
                :error="errors.title"
            />
            <Textarea
                id="rule-reason"
                v-model="form.reason"
                label="Why it matters"
                optional
                :maxlength="LIMITS.accounting.ruleReason"
                :error="errors.reason"
            />
            <div class="flex items-center gap-3">
                <Switch v-model="form.pinned" labelledby="rule-pinned-label" />
                <Text id="rule-pinned-label" as="span">Pin it to the top of the wall and the overview</Text>
            </div>
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="rule-form" :loading="saving">{{ rule ? 'Save' : 'Add rule' }}</Button>
        </template>
    </Dialog>
</template>

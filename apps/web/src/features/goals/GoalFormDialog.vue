<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import {
    GOAL_TONES,
    LIMITS,
    type Goal,
    type GoalInput,
    type GoalStatus,
    type GoalTimeframe,
    type GoalTone,
} from '@focus/types';
import { Button, Dialog, Input, Select, Textarea, useToast } from '@focus/ui';
import { computed, reactive, ref, watch } from 'vue';
import { endOfWeekIso, todayIso } from '@/lib/dates';
import { goalStatusOptions, goalTimeframeOptions, goalToneLabels } from '@/lib/labels';
import { toneDot } from '@/lib/tones';

const props = defineProps<{ goal?: Goal | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [goal: Goal] }>();

const toast = useToast();
const form = reactive({
    title: '',
    description: '' as string | null,
    status: 'active' as GoalStatus,
    timeframe: 'weekly' as GoalTimeframe,
    tone: 'green' as GoalTone,
    start_date: '' as string | null,
    target_date: '' as string | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.goal);

watch(open, (value) => {
    if (!value) return;
    const goal = props.goal;
    Object.assign(form, {
        title: goal?.title ?? '',
        description: goal?.description ?? '',
        status: goal?.status ?? 'active',
        timeframe: goal?.timeframe ?? 'weekly',
        tone: goal?.tone ?? 'green',
        start_date: goal ? (goal.start_date ?? '') : todayIso(),
        target_date: goal ? (goal.target_date ?? '') : endOfWeekIso(),
    });
    errors.value = {};
});

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) errors.value.title = 'Give the goal a title.';
    if (form.start_date && form.target_date && form.target_date < form.start_date) {
        errors.value.target_date = 'The target date must be on or after the start date.';
    }
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    const payload: GoalInput = {
        title: form.title.trim(),
        description: form.description?.trim() || null,
        status: form.status,
        timeframe: form.timeframe,
        tone: form.tone,
        start_date: form.start_date || null,
        target_date: form.target_date || null,
    };
    try {
        const saved = props.goal ? await api.goals.update(props.goal.id, payload) : await api.goals.create(payload);
        toast.success(isEdit.value ? 'Goal updated' : 'Goal created');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the goal.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit goal' : 'New goal'"
        description="Progress is calculated from the goal’s completed tasks."
    >
        <form id="goal-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="goal-title"
                v-model="form.title"
                label="Title"
                :maxlength="LIMITS.goal.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <Textarea
                id="goal-description"
                v-model="form.description"
                label="Description"
                optional
                :maxlength="LIMITS.goal.description"
                :error="errors.description"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Select
                    id="goal-timeframe"
                    v-model="form.timeframe"
                    label="Timeframe"
                    :options="goalTimeframeOptions"
                    :error="errors.timeframe"
                />
                <Select
                    id="goal-status"
                    v-model="form.status"
                    label="Status"
                    :options="goalStatusOptions"
                    :error="errors.status"
                />
                <Input
                    id="goal-start"
                    v-model="form.start_date"
                    type="date"
                    label="Start date"
                    optional
                    :error="errors.start_date"
                />
                <Input
                    id="goal-target"
                    v-model="form.target_date"
                    type="date"
                    label="Target date"
                    optional
                    :error="errors.target_date"
                />
            </div>
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-label font-semibold">Tone</legend>
                <p class="text-label text-fg-muted">
                    Colours this goal’s cards and tasks so jobs are easy to tell apart.
                </p>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="tone in GOAL_TONES"
                        :key="tone"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-label font-semibold transition-colors has-focus-visible:outline-2 has-focus-visible:outline-focus-ring',
                            form.tone === tone
                                ? 'border-fg bg-surface-raised'
                                : 'border-border-control hover:border-border-strong',
                        ]"
                    >
                        <input v-model="form.tone" type="radio" name="goal-tone" :value="tone" class="sr-only" />
                        <span :class="['size-3 rounded-full', toneDot[tone]]" aria-hidden="true" />
                        {{ goalToneLabels[tone] }}
                    </label>
                </div>
            </fieldset>
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" form="goal-form" :loading="saving">{{
                isEdit ? 'Save changes' : 'Create goal'
            }}</Button>
        </template>
    </Dialog>
</template>

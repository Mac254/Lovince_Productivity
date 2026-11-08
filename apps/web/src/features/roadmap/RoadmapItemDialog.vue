<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type FinancialGoal, type RoadmapItem, type RoadmapStage } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { roadmapStageOptions } from '@/lib/labels';
import { decimalsOf, isAmount, toDecimal } from '@/features/accounting/money';

const props = defineProps<{ item?: RoadmapItem | null; stage?: RoadmapStage }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [item: RoadmapItem] }>();

const toast = useToast();
const form = reactive({
    title: '',
    stage: 'next' as RoadmapStage,
    done_on: null as string | null,
    details: '' as string | null,
    goal_id: '' as number | '',
    money_goal_id: '' as number | '',
    target_amount: '',
    starts_on: null as string | null,
    ends_on: null as string | null,
});
const goalsStore = useGoalsStore();
const goalOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a goal' },
    ...goalsStore.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);
const moneyGoals = ref<FinancialGoal[]>([]);
const moneyGoalOptions = computed(() => [
    { value: '' as const, label: 'Not linked' },
    ...moneyGoals.value.map((goal) => ({ value: goal.id, label: goal.name })),
]);
const currency = computed(
    () =>
        moneyGoals.value.find((goal) => goal.id === form.money_goal_id)?.currency ??
        props.item?.money_goal?.currency ??
        'KES',
);
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.item);

watch(open, (value) => {
    if (!value) return;
    const item = props.item;
    Object.assign(form, {
        title: item?.title ?? '',
        stage: item?.stage ?? props.stage ?? 'next',
        done_on: item?.done_on ?? null,
        details: item?.details ?? '',
        goal_id: item?.goal_id ?? '',
        money_goal_id: item?.money_goal_id ?? '',
        target_amount:
            item?.target_amount != null ? toDecimal(item.target_amount, item.money_goal?.currency ?? 'KES') : '',
        starts_on: item?.starts_on ?? null,
        ends_on: item?.ends_on ?? null,
    });
    errors.value = {};
    void goalsStore.load().catch(() => []);
    api.accounting.goals
        .list()
        .then((goals) => (moneyGoals.value = goals))
        .catch(() => (moneyGoals.value = []));
});

watch(
    () => form.stage,
    (stage) => {
        if (stage === 'done' && !form.done_on) form.done_on = todayIso();
    },
);

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) {
        errors.value.title = 'Give the phase a name.';
        return;
    }
    const target = form.target_amount.trim();
    if (target && !isAmount(target, currency.value)) {
        errors.value.target_amount = 'Enter an amount, like 75000.';
        return;
    }
    saving.value = true;
    const payload = {
        title: form.title.trim(),
        stage: form.stage,
        done_on: form.stage === 'done' ? form.done_on : null,
        details: form.details?.trim() || null,
        goal_id: form.goal_id || null,
        money_goal_id: form.money_goal_id || null,
        target_amount: target
            ? Math.round(Number(target.replace(/[,\s]/g, '')) * 10 ** decimalsOf(currency.value))
            : null,
        starts_on: form.starts_on,
        ends_on: form.ends_on,
    };
    try {
        const saved = props.item ? await api.roadmap.update(props.item.id, payload) : await api.roadmap.create(payload);
        toast.success(isEdit.value ? 'Phase updated' : 'Phase added');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the roadmap item.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit phase' : 'Add a phase'"
        description="A step of your money plan: what you’ll do, over which months, how much it should reach and where its milestones come from."
        :busy="saving"
    >
        <form id="roadmap-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="roadmap-title"
                v-model="form.title"
                label="Phase"
                :maxlength="LIMITS.roadmap.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Combobox
                    id="roadmap-money-goal"
                    v-model="form.money_goal_id"
                    label="Money goal"
                    optional
                    hint="Its saved amount fills this phase’s bar"
                    :options="moneyGoalOptions"
                    :error="errors.money_goal_id"
                />
                <Input
                    id="roadmap-target"
                    v-model="form.target_amount"
                    :label="`Reach by the end (${currency})`"
                    optional
                    inputmode="decimal"
                    placeholder="The goal’s own target"
                    autocomplete="off"
                    :disabled="!form.money_goal_id"
                    :error="errors.target_amount"
                />
                <Select id="roadmap-stage" v-model="form.stage" label="Status" :options="roadmapStageOptions" />
                <Combobox
                    id="roadmap-goal"
                    v-model="form.goal_id"
                    label="Milestones from"
                    optional
                    hint="Its tasks due in this phase become the milestones"
                    :options="goalOptions"
                    :error="errors.goal_id"
                />
                <DatePicker
                    id="roadmap-starts"
                    v-model="form.starts_on"
                    label="Starts"
                    optional
                    :error="errors.starts_on"
                />
                <DatePicker id="roadmap-ends" v-model="form.ends_on" label="Ends" optional :error="errors.ends_on" />
                <DatePicker
                    v-if="form.stage === 'done'"
                    id="roadmap-done"
                    v-model="form.done_on"
                    label="Done on"
                    :error="errors.done_on"
                />
            </div>
            <Textarea
                id="roadmap-details"
                v-model="form.details"
                label="Details"
                optional
                hint="Why it matters, what done looks like"
                :maxlength="LIMITS.roadmap.details"
                :error="errors.details"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="roadmap-form" :loading="saving" icon-start="roadmap">{{
                isEdit ? 'Save changes' : 'Add phase'
            }}</Button>
        </template>
    </Dialog>
</template>

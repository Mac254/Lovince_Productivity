<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type FinancialGoal, type GoalContribution } from '@focus/types';
import { formatDate, todayIso } from '@/lib/dates';
import { decimalsOf, formatMoney, isAmount } from './money';

/** Records money put towards a goal without an account, or taken back out, and lists what was recorded. */
const props = defineProps<{ goal: FinancialGoal | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ changed: [] }>();

const toast = useToast();
const form = reactive({ direction: 'in' as 'in' | 'out', amount: '', date: todayIso(), note: '' });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const history = shallowRef<GoalContribution[]>([]);
const removingId = ref<number | null>(null);

const directions = [
    { value: 'in' as const, label: 'Put in' },
    { value: 'out' as const, label: 'Take out' },
];

async function loadHistory(): Promise<void> {
    if (!props.goal) return;
    try {
        history.value = await api.accounting.goals.contributions.list(props.goal.id);
    } catch (e) {
        toast.error(errorMessage(e, 'Could not load what you recorded.'));
    }
}

watch(open, (value) => {
    if (!value) return;
    Object.assign(form, { direction: 'in', amount: '', date: todayIso(), note: '' });
    errors.value = {};
    history.value = [];
    void loadHistory();
});

async function submit(): Promise<void> {
    const goal = props.goal;
    if (!goal) return;
    errors.value = {};
    if (!isAmount(form.amount, goal.currency)) {
        errors.value.amount = decimalsOf(goal.currency)
            ? `Enter an amount above zero with up to ${decimalsOf(goal.currency)} decimal places.`
            : 'Enter a whole amount above zero.';
        return;
    }
    saving.value = true;
    try {
        await api.accounting.goals.contributions.create(goal.id, {
            amount: form.amount.trim(),
            withdraw: form.direction === 'out',
            date: form.date || todayIso(),
            note: form.note.trim() || null,
        });
        toast.success(form.direction === 'out' ? 'Withdrawal recorded' : 'Money added');
        open.value = false;
        emit('changed');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not record it.'));
    } finally {
        saving.value = false;
    }
}

async function remove(entry: GoalContribution): Promise<void> {
    if (!props.goal) return;
    removingId.value = entry.id;
    try {
        await api.accounting.goals.contributions.remove(props.goal.id, entry.id);
        toast.success('Removed');
        await loadHistory();
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not remove it.'));
    } finally {
        removingId.value = null;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="goal ? goal.name : 'Goal'"
        description="Record money you set aside for this goal, or take some back out."
        :busy="saving"
    >
        <form v-if="goal" id="contribution-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <SegmentedControl v-model="form.direction" label="Direction" :options="directions" />
            <div class="grid gap-6 sm:grid-cols-2">
                <Input
                    id="contribution-amount"
                    v-model="form.amount"
                    :label="`Amount (${goal.currency})`"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="0.00"
                    required
                    autofocus
                    :error="errors.amount"
                />
                <DatePicker
                    id="contribution-date"
                    v-model="form.date"
                    label="Date"
                    :max="todayIso()"
                    :error="errors.date"
                />
            </div>
            <Input
                id="contribution-note"
                v-model="form.note"
                label="Note"
                optional
                :maxlength="LIMITS.accounting.description"
                :error="errors.note"
            />
        </form>
        <section v-if="history.length" aria-labelledby="contribution-history" class="mt-6 flex flex-col gap-2">
            <Text id="contribution-history" as="h3" role="label" tone="muted">Recorded so far</Text>
            <ul class="flex flex-col">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="flex items-center gap-3 border-b border-border py-2 last:border-b-0"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block truncate">{{
                            entry.note || (entry.amount < 0 ? 'Taken out' : 'Put in')
                        }}</span>
                        <span class="text-caption text-fg-muted">{{ formatDate(entry.date) }}</span>
                    </span>
                    <span
                        class="font-semibold whitespace-nowrap tabular"
                        :class="entry.amount > 0 && 'text-success-fg'"
                        >{{ formatMoney(entry.amount, goal!.currency, { signed: true }) }}</span
                    >
                    <IconButton
                        icon="trash"
                        size="sm"
                        variant="ghost"
                        :label="`Remove ${formatMoney(entry.amount, goal!.currency, { signed: true })} on ${formatDate(entry.date)}`"
                        :disabled="removingId !== null"
                        @click="remove(entry)"
                    />
                </li>
            </ul>
        </section>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Close</Button>
            <Button type="submit" form="contribution-form" :loading="saving">{{
                form.direction === 'out' ? 'Take out' : 'Add money'
            }}</Button>
        </template>
    </Dialog>
</template>

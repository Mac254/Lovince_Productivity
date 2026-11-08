<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Currency, FinancialGoal } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { daysUntil, formatDate } from '@/lib/dates';
import { useAccountingLists } from '@/features/accounting/useAccounting';
import { formatMoney, goalKindIcons, goalKindLabels, goalPercent } from '@/features/accounting/money';

const toast = useToast();
const lists = useAccountingLists();
const showArchived = ref(false);
const { data, loading, error, run } = useAsync((signal) => api.accounting.goals.list({ archived: true }, { signal }));

const goals = computed(() => (data.value ?? []).filter((g) => showArchived.value || !g.archived));
const archivedCount = computed(() => (data.value ?? []).filter((g) => g.archived).length);
const currencies = computed(() => [...new Set(lists.accounts.value.map((a) => a.currency))].sort() as Currency[]);

const totals = computed(() => {
    const open = (data.value ?? []).filter((g) => !g.archived);
    const currency = open[0]?.currency ?? currencies.value[0] ?? 'KES';
    const same = open.filter((g) => g.currency === currency);
    return {
        currency,
        mixed: same.length !== open.length,
        count: open.length,
        reached: open.filter((g) => g.achieved).length,
        saved: same.reduce((sum, g) => sum + Math.max(0, Math.min(g.saved, g.target_amount)), 0),
        target: same.reduce((sum, g) => sum + g.target_amount, 0),
        monthly: same.reduce((sum, g) => sum + (g.monthly_needed ?? 0), 0),
    };
});

const formOpen = ref(false);
const editing = ref<FinancialGoal | null>(null);
const contributing = ref<FinancialGoal | null>(null);
const contributionOpen = ref(false);
const archiving = ref<FinancialGoal | null>(null);
const deleting = ref<FinancialGoal | null>(null);
const busy = ref(false);

function open(goal: FinancialGoal | null): void {
    editing.value = goal;
    formOpen.value = true;
}

function contribute(goal: FinancialGoal): void {
    contributing.value = goal;
    contributionOpen.value = true;
}

function deadline(goal: FinancialGoal): string | null {
    if (!goal.target_date) return null;
    const days = daysUntil(goal.target_date);
    const date = formatDate(goal.target_date, { day: 'numeric', month: 'short', year: 'numeric' });
    if (goal.achieved) return `Target ${date}`;
    if (days < 0) return `Was due ${date}`;
    return days === 0 ? 'Due today' : `By ${date}`;
}

function menu(goal: FinancialGoal): MenuItem[] {
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => open(goal) },
        ...(!goal.account && !goal.archived
            ? [{ label: 'Add or take out money', icon: 'add' as const, onSelect: () => contribute(goal) }]
            : []),
        goal.archived
            ? { label: 'Restore', icon: 'undo', onSelect: () => setArchived(goal, false) }
            : { label: 'Archive', icon: 'lock', onSelect: () => (archiving.value = goal) },
        { label: 'Delete', icon: 'trash', destructive: true, onSelect: () => (deleting.value = goal) },
    ];
}

async function act(work: () => Promise<unknown>, success: string, failure: string): Promise<void> {
    busy.value = true;
    try {
        await work();
        toast.success(success);
        archiving.value = null;
        deleting.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, failure));
    } finally {
        busy.value = false;
    }
}

const setArchived = (goal: FinancialGoal, archived: boolean) =>
    act(
        () => api.accounting.goals.update(goal.id, { archived }),
        archived ? 'Goal archived' : 'Goal restored',
        'Could not update the goal.',
    );
const confirmDelete = () =>
    deleting.value &&
    act(() => api.accounting.goals.remove(deleting.value!.id), 'Goal deleted', 'Could not delete the goal.');

onMounted(() => Promise.all([run(), lists.load()]));
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Goals"
            title-id="page-title"
            description="Savings, an emergency fund or an investment: set a target and watch it fill up."
        >
            <template #actions>
                <Button icon-start="add" @click="open(null)">New goal</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 3" :key="n" class="h-48" />
        </div>
        <EmptyState
            v-else-if="data && !data.length"
            icon="target"
            title="No goals yet"
            message="Start with an emergency fund, or save towards something specific. Link the account you keep the money in, or record what you put aside."
        >
            <Button icon-start="add" @click="open(null)">New goal</Button>
        </EmptyState>
        <template v-else-if="data">
            <div v-if="totals.count" class="grid gap-4 sm:grid-cols-3">
                <MoneyTile
                    label="Saved"
                    :value="formatMoney(totals.saved, totals.currency)"
                    icon="wallet"
                    emphasis
                    :hint="`of ${formatMoney(totals.target, totals.currency)}${totals.mixed ? ` in ${totals.currency}` : ''}`"
                />
                <MoneyTile
                    label="Goals"
                    :value="String(totals.count)"
                    icon="target"
                    motion="pop"
                    :hint="totals.reached ? `${totals.reached} reached` : 'None reached yet'"
                />
                <MoneyTile
                    label="Each month"
                    :value="formatMoney(totals.monthly, totals.currency)"
                    icon="calendar-clock"
                    motion="nudge"
                    hint="To reach every dated goal on time"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="goal in goals"
                    :key="goal.id"
                    as="article"
                    class="flex flex-col gap-4"
                    :class="goal.archived && 'opacity-70'"
                    :aria-labelledby="`goal-${goal.id}`"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="inline-flex size-(--control-md) shrink-0 items-center justify-center rounded-md bg-surface-raised text-highlight-fg"
                            >
                                <Icon :name="goalKindIcons[goal.kind]" size="md" motion="pop" />
                            </span>
                            <div class="min-w-0">
                                <p :id="`goal-${goal.id}`" class="truncate font-semibold">{{ goal.name }}</p>
                                <Text as="p" role="caption" tone="muted" truncate>
                                    {{ goalKindLabels[goal.kind] }}
                                    <template v-if="goal.account"> · {{ goal.account.name }}</template>
                                    <template v-if="goal.archived"> · archived</template>
                                </Text>
                            </div>
                        </div>
                        <DropdownMenu :items="menu(goal)" :label="`Actions for ${goal.name}`" size="sm" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="font-display text-title font-semibold tracking-tight tabular">
                                {{ formatMoney(goal.saved, goal.currency) }}
                            </p>
                            <Badge v-if="goal.achieved" dot tone="success">Reached</Badge>
                            <span v-else class="text-label text-fg-muted tabular">{{ goalPercent(goal) }}%</span>
                        </div>
                        <ProgressBar
                            :value="goalPercent(goal)"
                            :tone="goal.achieved ? 'highlight' : 'accent'"
                            :label="`${goal.name}: ${goalPercent(goal)}% of the target saved`"
                        />
                        <Text as="p" role="caption" tone="muted" class="tabular">
                            {{ formatMoney(goal.saved, goal.currency) }} of
                            {{ formatMoney(goal.target_amount, goal.currency) }}
                        </Text>
                    </div>

                    <dl class="mt-auto grid grid-cols-2 gap-3 text-label">
                        <div>
                            <dt class="text-fg-muted">{{ goal.achieved ? 'Target' : 'Still to save' }}</dt>
                            <dd class="font-semibold tabular">
                                {{ formatMoney(goal.achieved ? goal.target_amount : goal.remaining, goal.currency) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-fg-muted">{{ deadline(goal) ?? 'No date' }}</dt>
                            <dd class="font-semibold tabular">
                                <template v-if="goal.monthly_needed !== null"
                                    >{{ formatMoney(goal.monthly_needed, goal.currency) }} a month</template
                                >
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>

                    <Button
                        v-if="!goal.account && !goal.archived"
                        variant="outline"
                        size="sm"
                        icon-start="add"
                        @click="contribute(goal)"
                        >Add money</Button
                    >
                    <Button
                        v-else-if="goal.account"
                        variant="ghost"
                        size="sm"
                        icon-end="arrow-right"
                        :to="{ name: 'transactions', query: { account: goal.account.id } }"
                        >{{ goal.account.name }} transactions</Button
                    >
                </Card>
            </div>
            <div v-if="archivedCount" class="flex items-center gap-3">
                <Switch v-model="showArchived" label="Show archived goals" />
                <Text as="span" tone="muted">Show {{ archivedCount }} archived</Text>
            </div>
        </template>

        <FinancialGoalFormDialog
            v-model:open="formOpen"
            :goal="editing"
            :accounts="lists.accounts.value"
            :currencies="currencies.length ? currencies : ['KES']"
            @saved="run"
        />
        <GoalContributionDialog v-model:open="contributionOpen" :goal="contributing" @changed="run" />
        <ConfirmDialog
            :open="!!archiving"
            title="Archive goal?"
            :message="`“${archiving?.name}” moves out of your goals list. You can restore it any time.`"
            confirm-label="Archive"
            :loading="busy"
            @update:open="!$event && (archiving = null)"
            @confirm="archiving && setArchived(archiving, true)"
        />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete goal?"
            :message="
                deleting?.account
                    ? `“${deleting?.name}” goes. ${deleting?.account.name} and its transactions stay as they are.`
                    : `“${deleting?.name}” and the money recorded against it go for good.`
            "
            :loading="busy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

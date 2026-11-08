<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { FinancialAccount, FinancialGoal, RoadmapItem, RoadmapStage } from '@focus/types';
import { roadmapStageLabels } from '@/lib/labels';
import { formatMoney, goalPercent } from '@/features/accounting/money';

const route = useRoute();
const toast = useToast();
const settings = useSettingsStore();
const formOpen = ref(false);
const editing = ref<RoadmapItem | null>(null);
const deleting = ref<RoadmapItem | null>(null);
const deleteBusy = ref(false);

const { data, loading, error, run } = useAsync(async (signal) => {
    const [items, accounts, goals] = await Promise.all([
        api.roadmap.list({ signal }),
        api.accounting.accounts.list({}, { signal }).catch(() => [] as FinancialAccount[]),
        api.accounting.goals.list({}, { signal }).catch(() => [] as FinancialGoal[]),
    ]);
    return { items, accounts, goals };
});

const order: RoadmapStage[] = ['done', 'now', 'next', 'later'];
const steps = computed(() =>
    order
        .filter((stage) => stage !== 'done' || settings.values.roadmap.show_done)
        .flatMap((stage) => {
            const items = (data.value?.items ?? []).filter((item) => item.stage === stage);
            if (stage === 'done') items.sort((a, b) => (a.done_on ?? '').localeCompare(b.done_on ?? '') || a.id - b.id);
            return items.map((item, index) => ({ item, first: index === 0, last: index === items.length - 1 }));
        }),
);
const current = computed(() => data.value?.items.find((item) => item.stage === 'now') ?? null);
const nextMilestone = computed(
    () =>
        (data.value?.items ?? [])
            .filter((item) => item.stage !== 'done')
            .flatMap((item) => item.milestones ?? [])
            .filter((milestone) => milestone.status !== 'completed' && milestone.status !== 'cancelled')
            .sort((a, b) => (a.due_date ?? '9999').localeCompare(b.due_date ?? '9999'))[0] ?? null,
);
const doneCount = computed(() => (data.value?.items ?? []).filter((item) => item.stage === 'done').length);

const goalTiles = computed(() =>
    (data.value?.goals ?? []).map((goal) => ({
        goal,
        value: formatMoney(goal.saved, goal.currency),
        hint: `${goalPercent(goal)}% of ${formatMoney(goal.target_amount, goal.currency)}`,
    })),
);
const invested = computed(() => {
    const accounts = (data.value?.accounts ?? []).filter(
        (account) => !account.archived && (account.type === 'sacco' || account.type === 'investment'),
    );
    if (!accounts.length) return null;
    return {
        value: formatMoney(
            accounts.reduce((sum, account) => sum + account.balance, 0),
            accounts[0]!.currency,
        ),
        hint: accounts.map((account) => account.name).join(', '),
    };
});
const highlighted = computed(() => Number(route.query.item) || null);

function add(): void {
    editing.value = null;
    formOpen.value = true;
}

function edit(item: RoadmapItem): void {
    editing.value = item;
    formOpen.value = true;
}

async function move(item: RoadmapItem, offset: -1 | 1): Promise<void> {
    const ids = (data.value?.items ?? []).filter((i) => i.stage === item.stage).map((i) => i.id);
    const from = ids.indexOf(item.id);
    const to = from + offset;
    if (to < 0 || to >= ids.length) return;
    [ids[from], ids[to]] = [ids[to]!, ids[from]!];
    try {
        const items = await api.roadmap.reorder(item.stage, ids);
        if (data.value) data.value = { ...data.value, items };
    } catch (e) {
        toast.error(errorMessage(e, 'Could not move the phase.'));
    }
}

async function changeStage(item: RoadmapItem, stage: RoadmapStage): Promise<void> {
    try {
        await api.roadmap.update(item.id, { stage });
        toast.success(
            stage === 'done'
                ? `“${item.title}” done`
                : stage === 'now'
                  ? `You’re on “${item.title}”`
                  : `Moved to ${roadmapStageLabels[stage]}`,
        );
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the phase.'));
    }
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deleteBusy.value = true;
    try {
        await api.roadmap.remove(deleting.value.id);
        toast.success('Phase deleted');
        deleting.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the phase.'));
    } finally {
        deleteBusy.value = false;
    }
}

watch(data, async () => {
    if (!highlighted.value) return;
    await nextTick();
    document.getElementById(`roadmap-item-${highlighted.value}`)?.scrollIntoView({ block: 'center' });
});
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Money roadmap"
            title-id="page-title"
            description="Where you are on your money plan, phase by phase. Bars fill from your real balances, and ticking off a milestone task moves its phase along."
        >
            <template #actions>
                <Button icon-start="add" @click="add">Add phase</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="flex flex-col gap-4" aria-busy="true">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Skeleton v-for="n in 4" :key="n" class="h-32" />
            </div>
            <Skeleton v-for="n in 3" :key="n" class="h-40" />
        </div>

        <template v-else-if="data">
            <section aria-labelledby="where-now" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="where-now" class="font-display text-title font-semibold tracking-tight">
                        Where you are now
                    </h2>
                    <Text v-if="current" role="label" tone="muted">
                        Phase {{ doneCount + 1 }} of {{ data.items.length }}: <strong>{{ current.title }}</strong>
                        <template v-if="nextMilestone"> · next up: {{ nextMilestone.title }}</template>
                    </Text>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MoneyTile
                        v-for="tile in goalTiles"
                        :key="tile.goal.id"
                        :label="tile.goal.name"
                        :value="tile.value"
                        icon="target"
                        :hint="tile.hint"
                    />
                    <MoneyTile
                        v-if="invested"
                        label="Invested"
                        :value="invested.value"
                        icon="arrow-up-right"
                        motion="lift"
                        :hint="invested.hint"
                    />
                </div>
            </section>

            <section aria-labelledby="journey" class="flex flex-col gap-4">
                <h2 id="journey" class="font-display text-title font-semibold tracking-tight">The journey</h2>
                <ol v-if="steps.length" class="flex flex-col">
                    <li v-for="(step, index) in steps" :key="step.item.id" class="relative flex gap-4 pb-6 last:pb-0">
                        <span
                            v-if="index < steps.length - 1"
                            class="absolute top-8 bottom-0 left-4 w-0.5 -translate-x-1/2 bg-border"
                            aria-hidden="true"
                        />
                        <span
                            :class="[
                                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2',
                                step.item.stage === 'done' && 'border-highlight bg-highlight text-highlight-fg',
                                step.item.stage === 'now' && 'border-accent bg-accent text-accent-fg',
                                (step.item.stage === 'next' || step.item.stage === 'later') &&
                                    'border-border-control bg-surface text-fg-muted',
                            ]"
                            aria-hidden="true"
                        >
                            <Icon v-if="step.item.stage === 'done'" name="check" />
                            <Text v-else as="span" role="caption" class="font-semibold" tabular>{{ index + 1 }}</Text>
                        </span>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Badge
                                :tone="
                                    step.item.stage === 'now'
                                        ? 'accent'
                                        : step.item.stage === 'done'
                                          ? 'success'
                                          : 'neutral'
                                "
                                class="self-start"
                                >{{ roadmapStageLabels[step.item.stage] }}</Badge
                            >
                            <RoadmapCard
                                :item="step.item"
                                :first="step.first"
                                :last="step.last"
                                :highlighted="step.item.id === highlighted"
                                @edit="edit"
                                @delete="deleting = $event"
                                @move="move"
                                @stage="changeStage"
                            />
                        </div>
                    </li>
                </ol>
                <EmptyState
                    v-else
                    icon="roadmap"
                    title="No phases yet"
                    message="Break your money plan into phases, like building an emergency fund, then saving, then investing."
                >
                    <Button icon-start="add" @click="add">Add a phase</Button>
                </EmptyState>
            </section>
        </template>

        <RoadmapItemDialog v-model:open="formOpen" :item="editing" stage="later" @saved="run" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete phase?"
            :message="`“${deleting?.title}” will be removed from the roadmap. Its goals and tasks are kept.`"
            :loading="deleteBusy"
            @update:open="(value: boolean) => !value && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

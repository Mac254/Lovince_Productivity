<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { MoneyRule, MoneyRuleSuggestion } from '@focus/types';
import type { MenuItem } from '@focus/ui';

const toast = useToast();
const showArchived = ref(false);
const { data, loading, error, run } = useAsync((signal) => api.accounting.rules.list({ archived: true }, { signal }));
const suggestions = shallowRef<MoneyRuleSuggestion[]>([]);

const open = computed(() => (data.value ?? []).filter((r) => !r.archived));
const rules = computed(() => (data.value ?? []).filter((r) => showArchived.value || !r.archived));
const archivedCount = computed(() => (data.value ?? []).length - open.value.length);

const formOpen = ref(false);
const editing = ref<MoneyRule | null>(null);
const deleting = ref<MoneyRule | null>(null);
const busy = ref(false);
const adding = ref<string | null>(null);

async function loadSuggestions(): Promise<void> {
    try {
        suggestions.value = await api.accounting.rules.suggestions();
    } catch {
        suggestions.value = [];
    }
}

const refresh = () => Promise.all([run(), loadSuggestions()]);

function edit(rule: MoneyRule | null): void {
    editing.value = rule;
    formOpen.value = true;
}

async function act(work: () => Promise<unknown>, success: string | null, failure: string): Promise<void> {
    busy.value = true;
    try {
        await work();
        if (success) toast.success(success);
        deleting.value = null;
        await refresh();
    } catch (e) {
        toast.error(errorMessage(e, failure));
    } finally {
        busy.value = false;
    }
}

function move(rule: MoneyRule, step: -1 | 1): void {
    const group = open.value.filter((r) => r.pinned === rule.pinned).map((r) => r.id);
    const from = group.indexOf(rule.id);
    const to = from + step;
    if (from < 0 || to < 0 || to >= group.length) return;
    [group[from], group[to]] = [group[to]!, group[from]!];
    void act(() => api.accounting.rules.order(group), null, 'Could not move the rule.');
}

function menu(rule: MoneyRule): MenuItem[] {
    const group = open.value.filter((r) => r.pinned === rule.pinned);
    const index = group.findIndex((r) => r.id === rule.id);
    const update = (input: Parameters<typeof api.accounting.rules.update>[1], success: string) =>
        act(() => api.accounting.rules.update(rule.id, input), success, 'Could not update the rule.');
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => edit(rule) },
        ...(rule.archived
            ? [
                  {
                      label: 'Restore',
                      icon: 'undo' as const,
                      onSelect: () => update({ archived: false }, 'Rule restored'),
                  },
              ]
            : [
                  rule.pinned
                      ? {
                            label: 'Unpin',
                            icon: 'star' as const,
                            onSelect: () => update({ pinned: false }, 'Rule unpinned'),
                        }
                      : {
                            label: 'Pin',
                            icon: 'star-filled' as const,
                            onSelect: () => update({ pinned: true }, 'Rule pinned'),
                        },
                  ...(index > 0
                      ? [{ label: 'Move up', icon: 'chevron-up' as const, onSelect: () => move(rule, -1) }]
                      : []),
                  ...(index >= 0 && index < group.length - 1
                      ? [{ label: 'Move down', icon: 'chevron-down' as const, onSelect: () => move(rule, 1) }]
                      : []),
                  {
                      label: 'Archive',
                      icon: 'lock' as const,
                      onSelect: () => update({ archived: true }, 'Rule archived'),
                  },
              ]),
        { label: 'Delete', icon: 'trash', destructive: true, onSelect: () => (deleting.value = rule) },
    ];
}

async function addSuggestion(suggestion: MoneyRuleSuggestion): Promise<void> {
    adding.value = suggestion.key;
    await act(
        () =>
            api.accounting.rules.create({
                title: suggestion.title,
                reason: suggestion.reason,
                suggestion: suggestion.key,
            }),
        'Rule added',
        'Could not add the rule.',
    );
    adding.value = null;
}

const confirmDelete = () =>
    deleting.value &&
    act(() => api.accounting.rules.remove(deleting.value!.id), 'Rule deleted', 'Could not delete the rule.');

onMounted(refresh);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Rules"
            title-id="page-title"
            description="The money rules you want to keep in sight, and why each one matters."
        >
            <template #actions>
                <Button icon-start="add" @click="edit(null)">New rule</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 3" :key="n" class="h-32" />
        </div>
        <EmptyState
            v-else-if="data && !open.length && !showArchived"
            icon="quote"
            title="No rules yet"
            message="Write the rules you want to live by with money, or start from the suggestions below, which come from your goals and spending."
        >
            <Button icon-start="add" @click="edit(null)">New rule</Button>
        </EmptyState>
        <template v-if="data && rules.length">
            <ul class="columns-1 gap-4 sm:columns-2 xl:columns-3" aria-labelledby="page-title">
                <li v-for="rule in rules" :key="rule.id" class="mb-4 break-inside-avoid">
                    <Card
                        :tone="rule.pinned && !rule.archived ? 'highlight' : 'default'"
                        class="flex flex-col gap-3"
                        :class="rule.archived && 'opacity-70'"
                        :aria-labelledby="`rule-${rule.id}`"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p :id="`rule-${rule.id}`" class="font-display text-title font-semibold tracking-tight">
                                {{ rule.title }}
                            </p>
                            <DropdownMenu :items="menu(rule)" :label="`Actions for ${rule.title}`" size="sm" />
                        </div>
                        <Text v-if="rule.reason" as="p" tone="muted">{{ rule.reason }}</Text>
                        <div v-if="rule.pinned || rule.archived" class="flex gap-2">
                            <Badge v-if="rule.pinned && !rule.archived" icon="star-filled" tone="highlight"
                                >Pinned</Badge
                            >
                            <Badge v-if="rule.archived">Archived</Badge>
                        </div>
                    </Card>
                </li>
            </ul>
        </template>
        <div v-if="archivedCount" class="flex items-center gap-3">
            <Switch v-model="showArchived" labelledby="show-archived-rules" />
            <Text id="show-archived-rules" as="span" tone="muted">Show {{ archivedCount }} archived</Text>
        </div>

        <Section
            v-if="suggestions.length"
            title="Suggested from your goals and spending"
            description="Worked out from your open goals and the last few months of spending. Add the ones that fit; you can reword them after."
        >
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="suggestion in suggestions" :key="suggestion.key">
                    <Card class="flex h-full flex-col gap-3">
                        <Badge :icon="suggestion.source === 'goals' ? 'target' : 'wallet'" class="self-start">
                            {{ suggestion.source === 'goals' ? 'From your goals' : 'From your spending' }}
                        </Badge>
                        <p class="font-semibold">{{ suggestion.title }}</p>
                        <Text as="p" role="caption" tone="muted">{{ suggestion.reason }}</Text>
                        <Button
                            variant="outline"
                            size="sm"
                            icon-start="add"
                            class="mt-auto self-start"
                            :loading="adding === suggestion.key"
                            :disabled="busy"
                            @click="addSuggestion(suggestion)"
                            >Add to my rules</Button
                        >
                    </Card>
                </li>
            </ul>
        </Section>

        <MoneyRuleFormDialog v-model:open="formOpen" :rule="editing" @saved="refresh" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete rule?"
            :message="`“${deleting?.title}” goes for good. Archive it instead to keep it out of sight.`"
            :loading="busy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

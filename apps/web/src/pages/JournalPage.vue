<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type JournalContext, type JournalEntry } from '@focus/types';
import { Button, Card, ChipGroup, Icon, IconButton, PageHeader, SearchField, Text, useToast } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import DayContext from '@/features/journal/DayContext.vue';
import JournalCalendar from '@/features/journal/JournalCalendar.vue';
import JournalEditor from '@/features/journal/JournalEditor.vue';
import JournalEntryList from '@/features/journal/JournalEntryList.vue';
import JournalPreview from '@/features/journal/JournalPreview.vue';
import { addMonths, monthGrid, monthOf, shiftDate } from '@/lib/calendar';
import { formatDate, formatLongDate, todayIso } from '@/lib/dates';
import { standupDraft } from '@/lib/standup';
import { usePreferencesStore } from '@/stores/preferences';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const preferences = usePreferencesStore();

const date = computed(() => {
    const value = route.query.date;
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && value <= todayIso() ? value : todayIso();
});
const isToday = computed(() => date.value === todayIso());

const body = ref('');
const saved = ref('');
const savedAt = ref<string | null>(null);
const status = ref<'idle' | 'loading' | 'saving' | 'saved' | 'error'>('loading');
const view = ref<'write' | 'preview'>('write');
const editor = ref<InstanceType<typeof JournalEditor> | null>(null);
const dirty = computed(() => body.value !== saved.value);
let autosave: number | undefined;

const month = ref(monthOf(date.value));
const previews = ref<Record<string, string>>({});
const recent = ref<Set<string>>(new Set());
const results = ref<JournalEntry[]>([]);
const search = ref('');
const monthEntries = computed(() =>
    Object.entries(previews.value)
        .filter(([day]) => day.startsWith(month.value))
        .map(([day, text]) => ({ date: day, body: text })),
);
const monthName = computed(() => formatDate(`${month.value}-01`, { month: 'long' }));

const context = ref<JournalContext | null>(null);
const contextLoading = ref(false);
let contextRequest: AbortController | null = null;

const views = [
    { value: 'write' as const, label: 'Write' },
    { value: 'preview' as const, label: 'Preview' },
];

/** Consecutive days with an entry, ending today (or yesterday, while today is still blank). */
const streak = computed(() => {
    let day = recent.value.has(todayIso()) ? todayIso() : shiftDate(todayIso(), -1);
    let count = 0;
    while (recent.value.has(day)) {
        count++;
        day = shiftDate(day, -1);
    }
    return count;
});

async function loadDay(): Promise<void> {
    status.value = 'loading';
    try {
        const entry = await api.journal.get(date.value);
        body.value = saved.value = entry?.body ?? '';
        savedAt.value = entry?.updated_at ?? null;
        status.value = 'idle';
    } catch (e) {
        status.value = 'error';
        toast.error(errorMessage(e, 'Could not load the journal.'));
    }
}

async function loadContext(): Promise<void> {
    contextRequest?.abort();
    const request = (contextRequest = new AbortController());
    contextLoading.value = true;
    try {
        context.value = await api.journal.context(date.value, { signal: request.signal });
    } catch (e) {
        if (!(isApiError(e) && e.isCancelled)) context.value = null;
    } finally {
        if (contextRequest === request) contextLoading.value = false;
    }
}

async function loadMonth(): Promise<void> {
    const days = monthGrid(month.value).flat();
    try {
        const { data } = await api.journal.list({ from: days[0]!.date, to: days.at(-1)!.date });
        previews.value = Object.fromEntries(data.map((entry) => [entry.date, entry.body]));
    } catch (e) {
        toast.error(errorMessage(e, 'Could not load this month.'));
    }
}

async function loadRecent(): Promise<void> {
    const { data } = await api.journal
        .list({ from: shiftDate(todayIso(), -62), to: todayIso() })
        .catch(() => ({ data: [] as JournalEntry[] }));
    recent.value = new Set(data.map((entry) => entry.date));
}

async function searchEntries(): Promise<void> {
    if (!search.value.trim()) {
        results.value = [];
        return;
    }
    results.value = (
        await api.journal.list({ search: search.value.trim() }).catch(() => ({ data: [] as JournalEntry[] }))
    ).data;
}

async function save(): Promise<void> {
    if (!dirty.value) return;
    window.clearTimeout(autosave);
    status.value = 'saving';
    const text = body.value;
    try {
        const entry = await api.journal.save(date.value, text);
        saved.value = text;
        savedAt.value = entry?.updated_at ?? null;
        status.value = 'saved';
        const next = { ...previews.value };
        const days = new Set(recent.value);
        if (text.trim()) {
            next[date.value] = text;
            days.add(date.value);
        } else {
            delete next[date.value];
            days.delete(date.value);
        }
        previews.value = next;
        recent.value = days;
    } catch (e) {
        status.value = 'error';
        toast.error(errorMessage(e, 'Could not save your entry.'));
    }
}

async function go(day: string): Promise<void> {
    if (day > todayIso()) return;
    await save();
    await router.push({ query: day === todayIso() ? {} : { date: day } });
}

function draft(): void {
    view.value = 'write';
    editor.value?.append(standupDraft(context.value));
    toast.success('Stand-up draft added — Ctrl+Z undoes it');
}

const statusText = computed(() => {
    if (status.value === 'loading') return 'Loading…';
    if (status.value === 'saving') return 'Saving…';
    if (status.value === 'error') return 'Not saved — try again';
    if (dirty.value) return 'Unsaved';
    if (!saved.value) return 'Empty';
    return savedAt.value ? `Saved ${formatDate(savedAt.value, { timeStyle: 'short' })}` : 'Saved';
});

watch(body, () => {
    if (!dirty.value) return;
    window.clearTimeout(autosave);
    autosave = window.setTimeout(save, 1500);
});

let searchTimer: number | undefined;
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchEntries, 300);
});
watch(date, (value) => {
    void loadDay();
    void loadContext();
    if (monthOf(value) !== month.value) month.value = monthOf(value);
});
watch(month, loadMonth);
onBeforeRouteLeave(async () => {
    await save();
});
onMounted(() => {
    void loadDay();
    void loadContext();
    void loadMonth();
    void loadRecent();
});
onBeforeUnmount(() => {
    window.clearTimeout(autosave);
    contextRequest?.abort();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Journal"
            title-id="page-title"
            description="A private devlog, one entry a day. It’s included in that day’s report."
        />

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="flex min-w-0 flex-col gap-6 xl:col-span-8">
                <Card as="section" aria-labelledby="entry-heading" padding="none">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-6"
                    >
                        <div class="flex min-w-0 items-center gap-1">
                            <IconButton
                                icon="chevron-left"
                                label="Previous day (Alt+←)"
                                variant="ghost"
                                size="sm"
                                motion="nudge-back"
                                @click="go(shiftDate(date, -1))"
                            />
                            <div class="min-w-0 px-1">
                                <h2 id="entry-heading" class="truncate font-display text-title font-semibold">
                                    {{ isToday ? 'Today' : formatDate(date, { weekday: 'long' }) }}
                                </h2>
                                <Text role="caption" tone="muted" class="font-mono">{{ date }}</Text>
                            </div>
                            <IconButton
                                icon="chevron-right"
                                label="Next day (Alt+→)"
                                variant="ghost"
                                size="sm"
                                motion="nudge"
                                :disabled="isToday"
                                @click="go(shiftDate(date, 1))"
                            />
                            <Button v-if="!isToday" variant="ghost" size="sm" @click="go(todayIso())">Today</Button>
                        </div>
                        <ChipGroup v-model="view" label="Editor view" :options="views" />
                    </div>

                    <JournalEditor
                        v-show="view === 'write'"
                        ref="editor"
                        v-model="body"
                        v-model:font="preferences.prefs.journalFont"
                        :label="`Journal entry for ${formatLongDate(date)}`"
                        :disabled="status === 'loading'"
                        :save-state="statusText"
                        @save="save"
                        @previous="go(shiftDate(date, -1))"
                        @next="go(shiftDate(date, 1))"
                    />
                    <div v-if="view === 'preview'" class="min-h-96 px-6 py-6">
                        <JournalPreview :source="body">
                            <template #empty>
                                <Text tone="muted">Nothing written for this day yet.</Text>
                            </template>
                        </JournalPreview>
                    </div>
                </Card>

                <DayContext :context="context" :loading="contextLoading" :is-today="isToday" @draft="draft" />
            </div>

            <aside aria-label="Your entries" class="flex min-w-0 flex-col gap-6 xl:order-first xl:col-span-4">
                <Card as="section" aria-labelledby="calendar-heading" class="gap-4">
                    <div class="flex items-center justify-between gap-2">
                        <IconButton
                            icon="chevron-left"
                            label="Previous month"
                            variant="ghost"
                            size="sm"
                            motion="nudge-back"
                            @click="month = addMonths(month, -1)"
                        />
                        <h2 id="calendar-heading" class="font-display text-lead font-semibold">
                            {{ formatDate(`${month}-01`, { month: 'long', year: 'numeric' }) }}
                        </h2>
                        <IconButton
                            icon="chevron-right"
                            label="Next month"
                            variant="ghost"
                            size="sm"
                            motion="nudge"
                            :disabled="month >= monthOf(todayIso())"
                            @click="month = addMonths(month, 1)"
                        />
                    </div>

                    <JournalCalendar v-model:month="month" compact :selected="date" :previews="previews" @select="go" />

                    <div
                        class="flex items-center gap-3 rounded-md bg-surface-raised px-3 py-2"
                        :aria-label="`Writing streak: ${streak} ${streak === 1 ? 'day' : 'days'}`"
                        role="status"
                    >
                        <Icon name="flame" :class="streak ? 'text-highlight-fg' : 'text-fg-subtle'" />
                        <Text as="span" role="label" weight="semibold" tabular
                            >{{ streak >= 63 ? '63+' : streak }}-day streak</Text
                        >
                        <Text as="span" role="caption" tone="muted" class="ml-auto">
                            {{ streak ? 'Keep it going' : 'Write today to start one' }}
                        </Text>
                    </div>
                </Card>

                <Card as="section" aria-labelledby="entries-heading" class="gap-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 id="entries-heading" class="font-display text-lead font-semibold">
                            {{ search.trim() ? 'Search results' : `Entries in ${monthName}` }}
                        </h2>
                        <Text as="span" role="caption" tone="muted" tabular aria-live="polite">
                            {{ search.trim() ? results.length : monthEntries.length }}
                            {{ (search.trim() ? results.length : monthEntries.length) === 1 ? 'entry' : 'entries' }}
                        </Text>
                    </div>

                    <SearchField
                        id="journal-search"
                        v-model="search"
                        label="Search entries"
                        placeholder="Search your journal"
                        :maxlength="LIMITS.search"
                    />

                    <JournalEntryList
                        v-if="search.trim() ? results.length : monthEntries.length"
                        :entries="search.trim() ? results : monthEntries"
                        :across-months="!!search.trim()"
                        :selected="date"
                        @select="go"
                    />
                    <Text v-else role="label" tone="muted">
                        {{ search.trim() ? 'No entries match.' : `Nothing written in ${monthName} yet.` }}
                    </Text>
                </Card>
            </aside>
        </div>
    </div>
</template>

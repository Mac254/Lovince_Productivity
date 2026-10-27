<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type JournalEntry } from '@focus/types';
import { Button, Card, Icon, IconButton, PageHeader, SearchField, Text, useToast } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import JournalCalendar from '@/features/journal/JournalCalendar.vue';
import { addMonths, monthGrid, monthOf, shiftDate } from '@/lib/calendar';
import { formatDate, formatLongDate, todayIso } from '@/lib/dates';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const date = computed(() => {
    const value = route.query.date;
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && value <= todayIso() ? value : todayIso();
});

const body = ref('');
const saved = ref('');
const status = ref<'idle' | 'loading' | 'saving' | 'saved' | 'error'>('loading');
const month = ref(monthOf(date.value));
const previews = ref<Record<string, string>>({});
const results = ref<JournalEntry[]>([]);
const search = ref('');
const monthCount = computed(() => Object.keys(previews.value).filter((d) => d.startsWith(month.value)).length);
const dirty = computed(() => body.value !== saved.value);
let autosave: number | undefined;

async function loadDay(): Promise<void> {
    status.value = 'loading';
    try {
        const entry = await api.journal.get(date.value);
        body.value = saved.value = entry?.body ?? '';
        status.value = 'idle';
    } catch (e) {
        status.value = 'error';
        toast.error(errorMessage(e, 'Could not load the journal.'));
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
        await api.journal.save(date.value, text);
        saved.value = text;
        status.value = 'saved';
        const next = { ...previews.value };
        if (text.trim()) next[date.value] = text;
        else delete next[date.value];
        previews.value = next;
    } catch (e) {
        status.value = 'error';
        toast.error(errorMessage(e, 'Could not save your entry.'));
    }
}

function onInput(): void {
    window.clearTimeout(autosave);
    autosave = window.setTimeout(save, 1500);
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        event.preventDefault();
        void save();
    }
}

async function go(day: string): Promise<void> {
    await save();
    await router.push({ query: day === todayIso() ? {} : { date: day } });
}

const statusText = computed(
    () =>
        ({
            idle: dirty.value ? 'Unsaved changes' : saved.value ? 'Saved' : 'Nothing written yet',
            loading: 'Loading…',
            saving: 'Saving…',
            saved: dirty.value ? 'Unsaved changes' : 'Saved',
            error: 'Not saved — try again',
        })[status.value],
);

let searchTimer: number | undefined;
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchEntries, 300);
});
watch(date, (value) => {
    void loadDay();
    if (monthOf(value) !== month.value) month.value = monthOf(value);
});
watch(month, loadMonth);
onBeforeRouteLeave(async () => {
    await save();
});
onMounted(() => {
    void loadDay();
    void loadMonth();
});
onBeforeUnmount(() => window.clearTimeout(autosave));
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Journal"
            title-id="page-title"
            description="A private note for each day. It’s included in that day’s report."
        />

        <div class="grid items-start gap-6 xl:grid-cols-5">
            <Card as="section" aria-labelledby="calendar-heading" class="gap-4 xl:col-span-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <IconButton
                            icon="chevron-left"
                            label="Previous month"
                            variant="ghost"
                            size="sm"
                            motion="nudge-back"
                            @click="month = addMonths(month, -1)"
                        />
                        <div class="min-w-40 text-center">
                            <h2 id="calendar-heading" class="font-display text-title font-semibold">
                                {{ formatDate(`${month}-01`, { month: 'long', year: 'numeric' }) }}
                            </h2>
                            <Text role="caption" tone="muted" tabular
                                >{{ monthCount }} {{ monthCount === 1 ? 'entry' : 'entries' }}</Text
                            >
                        </div>
                        <IconButton
                            icon="chevron-right"
                            label="Next month"
                            variant="ghost"
                            size="sm"
                            motion="nudge"
                            :disabled="month >= monthOf(todayIso())"
                            @click="month = addMonths(month, 1)"
                        />
                        <Button
                            v-if="month !== monthOf(todayIso()) || date !== todayIso()"
                            variant="outline"
                            size="sm"
                            @click="go(todayIso())"
                            >Today</Button
                        >
                    </div>
                    <SearchField
                        id="journal-search"
                        v-model="search"
                        label="Search entries"
                        placeholder="Search your journal"
                        :maxlength="LIMITS.search"
                        class="w-full sm:w-64"
                    />
                </div>

                <template v-if="search.trim()">
                    <Text role="label" tone="muted" aria-live="polite"
                        >{{ results.length }} {{ results.length === 1 ? 'entry matches' : 'entries match' }}</Text
                    >
                    <ul v-if="results.length" class="-mx-2 flex flex-col divide-y divide-border">
                        <li v-for="entry in results" :key="entry.id">
                            <button
                                type="button"
                                class="group flex w-full flex-col gap-1 rounded-md px-2 py-3 text-left transition-colors hover:bg-surface-raised"
                                @click="go(entry.date)"
                            >
                                <span class="flex items-center justify-between gap-2 text-label font-semibold">
                                    {{
                                        formatDate(entry.date, {
                                            weekday: 'short',
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })
                                    }}
                                    <Icon name="arrow-right" motion="nudge" class="text-fg-muted" />
                                </span>
                                <span class="line-clamp-2 text-caption text-fg-muted">{{ entry.body }}</span>
                            </button>
                        </li>
                    </ul>
                </template>
                <JournalCalendar v-else v-model:month="month" :selected="date" :previews="previews" @select="go" />
            </Card>

            <Card as="section" aria-labelledby="entry-heading" class="order-first gap-4 xl:order-none xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <IconButton
                            icon="chevron-left"
                            label="Previous day"
                            variant="ghost"
                            size="sm"
                            motion="nudge-back"
                            @click="go(shiftDate(date, -1))"
                        />
                        <h2 id="entry-heading" class="font-display text-title font-semibold">
                            {{ formatLongDate(date) }}
                        </h2>
                        <IconButton
                            icon="chevron-right"
                            label="Next day"
                            variant="ghost"
                            size="sm"
                            motion="nudge"
                            :disabled="date >= todayIso()"
                            @click="go(shiftDate(date, 1))"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Text as="span" role="caption" tone="muted" aria-live="polite">{{ statusText }}</Text>
                        <Button
                            size="sm"
                            icon-start="save"
                            :disabled="!dirty"
                            :loading="status === 'saving'"
                            @click="save"
                            >Save</Button
                        >
                    </div>
                </div>
                <label for="journal-body" class="sr-only">Journal entry for {{ formatLongDate(date) }}</label>
                <textarea
                    id="journal-body"
                    v-model="body"
                    :maxlength="LIMITS.journal"
                    rows="18"
                    placeholder="What did you work on? What went well, what got in the way, what’s next?"
                    class="min-h-96 w-full resize-y rounded-md border border-border-control bg-surface p-4 text-body leading-relaxed text-fg placeholder:text-fg-subtle hover:border-border-strong focus-visible:border-highlight-fg focus-visible:outline-none"
                    :disabled="status === 'loading'"
                    @input="onInput"
                    @keydown="onKeydown"
                />
                <Text role="caption" tone="muted" class="text-right tabular"
                    >{{ body.length.toLocaleString() }} / {{ LIMITS.journal.toLocaleString() }} · Ctrl+S to save</Text
                >
            </Card>
        </div>
    </div>
</template>

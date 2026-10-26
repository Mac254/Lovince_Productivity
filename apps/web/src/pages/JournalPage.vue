<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type JournalEntry } from '@focus/types';
import { Button, Card, CardHeader, Icon, IconButton, PageHeader, SearchField, Text, useToast } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { formatDate, formatLongDate, parseDate, todayIso } from '@/lib/dates';

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
const entries = ref<JournalEntry[]>([]);
const search = ref('');
const dirty = computed(() => body.value !== saved.value);
let autosave: number | undefined;

function shift(days: number): string {
    const d = parseDate(date.value);
    d.setDate(d.getDate() + days);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

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

async function loadEntries(): Promise<void> {
    entries.value = (
        await api.journal.list(search.value || undefined).catch(() => ({ data: [] as JournalEntry[] }))
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
        void loadEntries();
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
    searchTimer = window.setTimeout(loadEntries, 300);
});
watch(date, loadDay);
onBeforeRouteLeave(async () => {
    await save();
});
onMounted(() => {
    void loadDay();
    void loadEntries();
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

        <div class="grid gap-6 lg:grid-cols-3">
            <Card as="section" aria-labelledby="entry-heading" class="group gap-4 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <IconButton
                            icon="chevron-left"
                            label="Previous day"
                            variant="ghost"
                            size="sm"
                            motion="nudge-back"
                            @click="go(shift(-1))"
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
                            @click="go(shift(1))"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Text as="span" role="caption" tone="muted" aria-live="polite">{{ statusText }}</Text>
                        <Button v-if="date !== todayIso()" variant="ghost" size="sm" @click="go(todayIso())"
                            >Today</Button
                        >
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
                    rows="16"
                    placeholder="What did you work on? What went well, what got in the way, what’s next?"
                    class="min-h-80 w-full resize-y rounded-md border border-border-control bg-surface p-4 text-body leading-relaxed text-fg placeholder:text-fg-subtle hover:border-border-strong focus-visible:border-highlight-fg focus-visible:outline-none"
                    :disabled="status === 'loading'"
                    @input="onInput"
                    @keydown="onKeydown"
                />
                <Text role="caption" tone="muted" class="text-right tabular"
                    >{{ body.length.toLocaleString() }} / {{ LIMITS.journal.toLocaleString() }} · Ctrl+S to save</Text
                >
            </Card>

            <Card as="aside" aria-labelledby="entries-heading" class="group h-fit gap-3">
                <CardHeader title="Entries" title-id="entries-heading" icon="journal" />
                <SearchField
                    id="journal-search"
                    v-model="search"
                    label="Search entries"
                    placeholder="Search your journal"
                />
                <ul v-if="entries.length" class="-mx-2 flex max-h-96 flex-col overflow-y-auto">
                    <li v-for="entry in entries" :key="entry.id">
                        <button
                            type="button"
                            :class="[
                                'group flex w-full flex-col gap-1 rounded-md px-2 py-2 text-left transition-colors hover:bg-surface-raised',
                                entry.date === date && 'bg-surface-raised',
                            ]"
                            :aria-current="entry.date === date ? 'date' : undefined"
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
                <Text v-else role="label" tone="muted">{{ search ? 'No entries match.' : 'No entries yet.' }}</Text>
            </Card>
        </div>
    </div>
</template>

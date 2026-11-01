<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Schedule, type ScheduleItem } from '@focus/types';
import { formatMinutes } from '@/lib/dates';
import { dayPresets, daySections, presetOf, sectionOf, type DayPreset, type DaySection } from './schedules';
import { clockTime, parseClockTime } from './timeline';

const props = defineProps<{ schedule: Schedule | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [Schedule] }>();

const toast = useToast();
const types = useTaskTypesStore();
const goals = useGoalsStore();
const uid = useId();
const fid = (name: string) => `${uid}-${name}`;

interface Row {
    key: number;
    title: string;
    start: string;
    minutes: number | '' | null;
    type: number | '' | null;
    goal: number | '' | null;
    section: DaySection;
}

let nextKey = 0;
const name = ref('');
const preset = ref<DayPreset>('every');
const rows = ref<Row[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const section = ref<DaySection>('morning');

const sectionStarts: Record<DaySection, number> = { morning: 6 * 60, afternoon: 12 * 60, evening: 17 * 60 };
const indexed = computed(() => rows.value.map((row, index) => ({ row, index })));
const sections = computed(() =>
    daySections.map((option) => {
        const entries = indexed.value.filter((entry) => entry.row.section === option.value);
        return {
            ...option,
            entries,
            minutes: entries.reduce((sum, entry) => sum + Number(entry.row.minutes || 0), 0),
            invalid: entries.some((entry) =>
                Object.keys(errors.value).some((key) => key.startsWith(`items.${entry.index}.`)),
            ),
        };
    }),
);
const current = computed(() => sections.value.find((option) => option.value === section.value)!);
const total = computed(() => formatMinutes(sections.value.reduce((sum, option) => sum + option.minutes, 0)));

const durations = [5, 10, 15, 20, 30, 40, 45, 60, 90, 120, 180, 240, 300, 360, 450, 480].map((value) => ({
    value,
    label: formatMinutes(value),
}));
const typeOptions = computed(() => [
    { value: '' as const, label: 'No type' },
    ...types.types.map((type) => ({ value: type.id, label: type.name })),
]);
const goalOptions = computed(() => [
    { value: '' as const, label: 'No goal' },
    ...goals.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);

const toRow = (item: ScheduleItem): Row => ({
    key: nextKey++,
    title: item.title,
    start: clockTime(item.start_minute),
    minutes: item.minutes,
    type: item.task_type_id ?? '',
    goal: item.goal_id ?? '',
    section: sectionOf(item.start_minute),
});

function addRow(): void {
    const last = current.value.entries.at(-1)?.row;
    const start = last
        ? Math.min((parseClockTime(last.start) ?? 0) + Number(last.minutes || 0), 23 * 60 + 30)
        : sectionStarts[section.value];
    rows.value.push({
        key: nextKey++,
        title: '',
        start: clockTime(start),
        minutes: 30,
        type: '',
        goal: '',
        section: section.value,
    });
}

function regroup(row: Row): void {
    const start = parseClockTime(row.start);
    if (start === null) return;
    row.section = sectionOf(start);
    section.value = row.section;
}

function removeRow(index: number): void {
    rows.value.splice(index, 1);
}

watch(open, (value) => {
    if (!value) return;
    void types.load();
    void goals.load();
    name.value = props.schedule?.name ?? '';
    preset.value = presetOf(props.schedule?.days ?? []);
    rows.value = (props.schedule?.items ?? []).map(toRow);
    errors.value = {};
    section.value = rows.value[0]?.section ?? 'morning';
    if (!rows.value.length) addRow();
});

async function submit(): Promise<void> {
    const items: ScheduleItem[] = [];
    for (const [index, row] of rows.value.entries()) {
        const start = parseClockTime(row.start);
        if (start === null) {
            errors.value = { [`items.${index}.start_minute`]: 'Enter a time like 09:30.' };
            section.value = row.section;
            return;
        }
        items.push({
            title: row.title.trim(),
            start_minute: start,
            minutes: Number(row.minutes),
            task_type_id: row.type === '' || row.type === null ? null : row.type,
            goal_id: row.goal === '' || row.goal === null ? null : row.goal,
        });
    }
    saving.value = true;
    errors.value = {};
    const input = {
        name: name.value.trim(),
        days: dayPresets.find((option) => option.value === preset.value)?.days ?? [],
        items,
    };
    try {
        const saved = props.schedule
            ? await api.schedules.update(props.schedule.id, input)
            : await api.schedules.create(input);
        toast.success(props.schedule ? 'Schedule updated' : 'Schedule created');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) {
            errors.value = e.fieldErrors();
            const failing = sections.value.find((option) => option.invalid);
            if (failing) section.value = failing.value;
        } else toast.error(errorMessage(e, 'Could not save the schedule.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        size="lg"
        :title="schedule ? 'Edit schedule' : 'New schedule'"
        description="Group your routine by part of the day. Changes apply the next time you plan a day with it."
    >
        <form :id="fid('form')" class="grid gap-8 lg:grid-cols-3" novalidate @submit.prevent="submit">
            <aside class="flex flex-col gap-6 lg:sticky lg:top-0 lg:self-start lg:border-r lg:border-border lg:pr-8">
                <Input
                    :id="fid('name')"
                    v-model="name"
                    label="Name"
                    placeholder="e.g. Workday routine"
                    :maxlength="LIMITS.schedule.name"
                    :error="errors.name"
                />
                <ChipGroup v-model="preset" label="Repeats on" :options="dayPresets" />
                <nav :aria-label="`${name || 'Schedule'} sections`" class="flex flex-col gap-1">
                    <Text role="caption" tone="muted" class="mb-1 font-semibold uppercase">Sections</Text>
                    <button
                        v-for="option in sections"
                        :key="option.value"
                        type="button"
                        :aria-current="option.value === section ? 'true' : undefined"
                        :class="[
                            'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left transition-colors duration-(--duration-fast) focus-visible:outline-2 focus-visible:outline-focus-ring',
                            option.value === section
                                ? 'bg-surface-raised text-fg shadow-card'
                                : 'text-fg-muted hover:bg-surface-raised hover:text-fg',
                        ]"
                        @click="section = option.value"
                    >
                        <span class="flex flex-col">
                            <span class="flex items-center gap-2 text-label font-semibold">
                                {{ option.label }}
                                <Icon v-if="option.invalid" name="alert-circle" size="sm" class="text-danger-fg" />
                            </span>
                            <span class="text-caption text-fg-muted tabular">{{
                                option.minutes ? formatMinutes(option.minutes) : 'Nothing yet'
                            }}</span>
                        </span>
                        <span
                            class="inline-flex min-w-(--control-sm) justify-center rounded-full bg-border px-2 text-caption font-bold tabular"
                            >{{ option.entries.length }}</span
                        >
                    </button>
                    <Text role="caption" tone="muted" tabular class="mt-2 px-3"
                        >{{ rows.length }} activities · {{ total }}</Text
                    >
                </nav>
            </aside>

            <section class="flex min-w-0 flex-col gap-4 lg:col-span-2" :aria-label="`${current.label} activities`">
                <div class="flex items-center justify-between gap-3">
                    <Heading :level="3" role="title">{{ current.label }}</Heading>
                    <Button
                        variant="outline"
                        size="sm"
                        icon-start="add"
                        :disabled="rows.length >= LIMITS.schedule.items"
                        @click="addRow"
                        >Add activity</Button
                    >
                </div>
                <Alert v-if="errors.items" tone="danger">{{ errors.items }}</Alert>
                <ol v-if="current.entries.length" class="flex flex-col gap-3">
                    <li
                        v-for="{ row, index } in current.entries"
                        :key="row.key"
                        class="flex flex-col gap-4 rounded-lg border border-border bg-surface p-4"
                    >
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-48 flex-1">
                                <Input
                                    :id="fid(`title-${row.key}`)"
                                    v-model="row.title"
                                    label="Activity"
                                    placeholder="e.g. Exercise"
                                    :maxlength="LIMITS.timeBlock.title"
                                    :error="errors[`items.${index}.title`]"
                                />
                            </div>
                            <div class="w-36">
                                <Input
                                    :id="fid(`start-${row.key}`)"
                                    v-model="row.start"
                                    type="time"
                                    label="Starts"
                                    :error="errors[`items.${index}.start_minute`]"
                                    @change="regroup(row)"
                                />
                            </div>
                            <div class="w-36">
                                <Select
                                    :id="fid(`minutes-${row.key}`)"
                                    v-model="row.minutes"
                                    label="Length"
                                    :options="durations"
                                    :error="errors[`items.${index}.minutes`]"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-48 flex-1">
                                <Select
                                    :id="fid(`type-${row.key}`)"
                                    v-model="row.type"
                                    label="Type"
                                    :options="typeOptions"
                                />
                            </div>
                            <div class="min-w-48 flex-1">
                                <Select
                                    :id="fid(`goal-${row.key}`)"
                                    v-model="row.goal"
                                    label="Goal"
                                    :options="goalOptions"
                                />
                            </div>
                            <IconButton
                                icon="trash"
                                :label="`Remove ${row.title || 'activity'}`"
                                variant="ghost"
                                :disabled="rows.length === 1"
                                @click="removeRow(index)"
                            />
                        </div>
                    </li>
                </ol>
                <EmptyState
                    v-else
                    icon="calendar-clock"
                    :title="`Nothing in the ${current.label.toLowerCase()}`"
                    message="Add an activity to fill this part of the day."
                />
            </section>
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                schedule ? 'Save changes' : 'Create schedule'
            }}</Button>
        </template>
    </Dialog>
</template>

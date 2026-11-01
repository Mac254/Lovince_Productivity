<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Schedule, type ScheduleItem } from '@focus/types';
import { formatMinutes } from '@/lib/dates';
import { dayPresets, presetOf, type DayPreset } from './schedules';
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
}

let nextKey = 0;
const name = ref('');
const preset = ref<DayPreset>('every');
const rows = ref<Row[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

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
});

function addRow(): void {
    const last = rows.value.at(-1);
    const start = last ? (parseClockTime(last.start) ?? 0) + Number(last.minutes || 0) : 8 * 60;
    rows.value.push({ key: nextKey++, title: '', start: clockTime(start), minutes: 30, type: '', goal: '' });
}

watch(open, (value) => {
    if (!value) return;
    void types.load();
    void goals.load();
    name.value = props.schedule?.name ?? '';
    preset.value = presetOf(props.schedule?.days ?? []);
    rows.value = (props.schedule?.items ?? []).map(toRow);
    if (!rows.value.length) addRow();
    errors.value = {};
});

async function submit(): Promise<void> {
    const items: ScheduleItem[] = [];
    for (const [index, row] of rows.value.entries()) {
        const start = parseClockTime(row.start);
        if (start === null) {
            errors.value = { [`items.${index}.start_minute`]: 'Enter a time like 09:30.' };
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
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the schedule.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="schedule ? 'Edit schedule' : 'New schedule'">
        <form :id="fid('form')" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                :id="fid('name')"
                v-model="name"
                label="Name"
                placeholder="e.g. Workday routine"
                :maxlength="LIMITS.schedule.name"
                :error="errors.name"
            />
            <ChipGroup v-model="preset" label="Repeats on" :options="dayPresets" />
            <Alert v-if="errors.items" tone="danger">{{ errors.items }}</Alert>
            <ol class="flex flex-col gap-4">
                <li
                    v-for="(row, index) in rows"
                    :key="row.key"
                    class="flex flex-col gap-4 rounded-lg border border-border p-4"
                >
                    <div class="flex items-end gap-3">
                        <div class="min-w-0 flex-1">
                            <Input
                                :id="fid(`title-${row.key}`)"
                                v-model="row.title"
                                label="Activity"
                                placeholder="e.g. Exercise"
                                :maxlength="LIMITS.timeBlock.title"
                                :error="errors[`items.${index}.title`]"
                            />
                        </div>
                        <IconButton
                            icon="trash"
                            :label="`Remove ${row.title || 'activity'}`"
                            variant="ghost"
                            :disabled="rows.length === 1"
                            @click="rows.splice(index, 1)"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            :id="fid(`start-${row.key}`)"
                            v-model="row.start"
                            type="time"
                            label="Starts"
                            :error="errors[`items.${index}.start_minute`]"
                        />
                        <Select
                            :id="fid(`minutes-${row.key}`)"
                            v-model="row.minutes"
                            label="Length"
                            :options="durations"
                            :error="errors[`items.${index}.minutes`]"
                        />
                        <Select :id="fid(`type-${row.key}`)" v-model="row.type" label="Type" :options="typeOptions" />
                        <Select :id="fid(`goal-${row.key}`)" v-model="row.goal" label="Goal" :options="goalOptions" />
                    </div>
                </li>
            </ol>
            <Button
                variant="outline"
                icon-start="add"
                class="self-start"
                :disabled="rows.length >= LIMITS.schedule.items"
                @click="addRow"
                >Add activity</Button
            >
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit" :form="fid('form')" :loading="saving">{{
                schedule ? 'Save changes' : 'Create schedule'
            }}</Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { Schedule } from '@focus/types';
import { formatMinutes, todayIso } from '@/lib/dates';
import { dayPresets, daySections, presetOf, sectionOf, type DaySection } from './schedules';
import { clockTime } from './timeline';

const props = defineProps<{ schedule: Schedule }>();
const emit = defineEmits<{ edit: []; changed: [] }>();

const toast = useToast();
const uid = useId();
const date = ref(todayIso(new Date(Date.now() + 86_400_000)));
const applying = ref(false);
const clash = ref('');
const deleting = ref(false);
const deleteBusy = ref(false);
const expanded = ref(true);

const grouped = computed(() =>
    daySections
        .map((option) => ({
            ...option,
            items: props.schedule.items.filter((item) => sectionOf(item.start_minute) === option.value),
        }))
        .filter((option) => option.items.length),
);
const section = ref<DaySection>(grouped.value[0]?.value ?? 'morning');
const sectionOptions = computed(() =>
    grouped.value.map((option) => ({ value: option.value, label: `${option.label} · ${option.items.length}` })),
);
const shown = computed(() => grouped.value.find((option) => option.value === section.value)?.items ?? []);
watch(grouped, (options) => {
    if (!options.some((option) => option.value === section.value)) section.value = options[0]?.value ?? 'morning';
});

const repeats = computed(() => dayPresets.find((preset) => preset.value === presetOf(props.schedule.days))?.label);
const total = computed(() => formatMinutes(props.schedule.items.reduce((sum, item) => sum + item.minutes, 0)));
const menu = computed(() => [
    { label: 'Edit', icon: 'edit' as const, onSelect: () => emit('edit') },
    { label: 'Delete', icon: 'trash' as const, destructive: true, onSelect: () => (deleting.value = true) },
]);

async function apply(replace = false): Promise<void> {
    if (!date.value) return;
    applying.value = true;
    try {
        const blocks = await api.schedules.apply(props.schedule.id, date.value, replace);
        clash.value = '';
        toast.success(`${blocks.length} blocks planned`);
    } catch (e) {
        if (isApiError(e) && e.isValidation) clash.value = e.fieldErrors().date ?? '';
        else toast.error(errorMessage(e, 'Could not apply the schedule.'));
    } finally {
        applying.value = false;
    }
}

async function remove(): Promise<void> {
    deleteBusy.value = true;
    try {
        await api.schedules.remove(props.schedule.id);
        toast.success('Schedule deleted');
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the schedule.'));
    } finally {
        deleteBusy.value = false;
    }
}
</script>

<template>
    <Card as="section" :aria-labelledby="`${uid}-title`" class="group">
        <CardHeader
            :title="schedule.name"
            :title-id="`${uid}-title`"
            icon="calendar-clock"
            :subtitle="`${repeats} · ${schedule.items.length} activities · ${total}`"
        >
            <div class="flex items-center gap-1">
                <IconButton
                    :icon="expanded ? 'chevron-up' : 'chevron-down'"
                    :label="expanded ? `Collapse ${schedule.name}` : `Expand ${schedule.name}`"
                    :aria-expanded="expanded"
                    :aria-controls="`${uid}-body`"
                    variant="ghost"
                    size="sm"
                    @click="expanded = !expanded"
                />
                <DropdownMenu :items="menu" :label="`${schedule.name} actions`" trigger-variant="ghost" size="sm" />
            </div>
        </CardHeader>

        <div v-show="expanded" :id="`${uid}-body`" class="flex flex-col gap-4">
            <ChipGroup
                v-if="sectionOptions.length > 1"
                v-model="section"
                :label="`${schedule.name} part of day`"
                :options="sectionOptions"
            />
            <ol class="flex flex-col">
                <li
                    v-for="item in shown"
                    :key="item.id"
                    class="flex items-baseline gap-4 border-t border-border py-2 first:border-t-0"
                >
                    <Text role="label" tone="muted" tabular class="w-28 shrink-0"
                        >{{ clockTime(item.start_minute) }}–{{ clockTime(item.start_minute + item.minutes) }}</Text
                    >
                    <Text role="label" class="truncate font-semibold">{{ item.title }}</Text>
                </li>
            </ol>
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-3" novalidate @submit.prevent="apply()">
            <div class="min-w-48 flex-1">
                <DatePicker :id="`${uid}-date`" v-model="date" label="Plan a day" />
            </div>
            <Button type="submit" icon-start="calendar-check" :loading="applying">Apply</Button>
        </form>
        <Alert v-if="clash" tone="warning" class="mt-4">
            {{ clash }}
            <Button variant="outline" size="sm" class="mt-3" :loading="applying" @click="apply(true)"
                >Replace that day's plan</Button
            >
        </Alert>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete schedule?"
            :message="`“${schedule.name}” will be deleted. Days you already planned with it stay as they are.`"
            :loading="deleteBusy"
            @confirm="remove"
        />
    </Card>
</template>

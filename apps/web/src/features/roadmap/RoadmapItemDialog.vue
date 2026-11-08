<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type RoadmapItem, type RoadmapStage } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { roadmapStageOptions } from '@/lib/labels';

const props = defineProps<{ item?: RoadmapItem | null; stage?: RoadmapStage }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [item: RoadmapItem] }>();

const toast = useToast();
const form = reactive({
    title: '',
    area: '' as string | null,
    stage: 'next' as RoadmapStage,
    done_on: null as string | null,
    details: '' as string | null,
    goal_id: '' as number | '',
    starts_on: null as string | null,
    ends_on: null as string | null,
});
const goalsStore = useGoalsStore();
const goalOptions = computed(() => [
    { value: '' as const, label: 'Not linked to a goal' },
    ...goalsStore.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.item);

watch(open, (value) => {
    if (!value) return;
    const item = props.item;
    Object.assign(form, {
        title: item?.title ?? '',
        area: item?.area ?? '',
        stage: item?.stage ?? props.stage ?? 'next',
        done_on: item?.done_on ?? null,
        details: item?.details ?? '',
        goal_id: item?.goal_id ?? '',
        starts_on: item?.starts_on ?? null,
        ends_on: item?.ends_on ?? null,
    });
    errors.value = {};
    void goalsStore.load().catch(() => []);
});

watch(
    () => form.stage,
    (stage) => {
        if (stage === 'done' && !form.done_on) form.done_on = todayIso();
    },
);

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) {
        errors.value.title = 'Give the phase a name.';
        return;
    }
    saving.value = true;
    const payload = {
        title: form.title.trim(),
        area: form.area?.trim() || null,
        stage: form.stage,
        done_on: form.stage === 'done' ? form.done_on : null,
        details: form.details?.trim() || null,
        goal_id: form.goal_id || null,
        starts_on: form.starts_on,
        ends_on: form.ends_on,
    };
    try {
        const saved = props.item ? await api.roadmap.update(props.item.id, payload) : await api.roadmap.create(payload);
        toast.success(isEdit.value ? 'Phase updated' : 'Phase added');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the roadmap item.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit phase' : 'Add a phase'"
        description="A phase of a plan: what you’ll do, over which months, and the goal whose milestones it follows."
        :busy="saving"
    >
        <form id="roadmap-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="roadmap-title"
                v-model="form.title"
                label="Phase"
                :maxlength="LIMITS.roadmap.title"
                required
                autocomplete="off"
                :error="errors.title"
                autofocus
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Input
                    id="roadmap-area"
                    v-model="form.area"
                    label="Area"
                    optional
                    placeholder="e.g. Money"
                    :maxlength="LIMITS.roadmap.area"
                    autocomplete="off"
                    :error="errors.area"
                />
                <Select id="roadmap-stage" v-model="form.stage" label="When" :options="roadmapStageOptions" />
                <Combobox
                    id="roadmap-goal"
                    v-model="form.goal_id"
                    label="Goal"
                    optional
                    hint="Its tasks due in this phase become the milestones"
                    :options="goalOptions"
                    :error="errors.goal_id"
                />
                <DatePicker
                    id="roadmap-starts"
                    v-model="form.starts_on"
                    label="Starts"
                    optional
                    :error="errors.starts_on"
                />
                <DatePicker id="roadmap-ends" v-model="form.ends_on" label="Ends" optional :error="errors.ends_on" />
                <DatePicker
                    v-if="form.stage === 'done'"
                    id="roadmap-done"
                    v-model="form.done_on"
                    label="Done on"
                    :error="errors.done_on"
                />
            </div>
            <Textarea
                id="roadmap-details"
                v-model="form.details"
                label="Details"
                optional
                hint="Why it matters, what done looks like"
                :maxlength="LIMITS.roadmap.details"
                :error="errors.details"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="roadmap-form" :loading="saving" icon-start="roadmap">{{
                isEdit ? 'Save changes' : 'Add phase'
            }}</Button>
        </template>
    </Dialog>
</template>

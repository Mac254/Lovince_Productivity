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
    shipped_on: null as string | null,
    details: '' as string | null,
});
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
        shipped_on: item?.shipped_on ?? null,
        details: item?.details ?? '',
    });
    errors.value = {};
});

watch(
    () => form.stage,
    (stage) => {
        if (stage === 'shipped' && !form.shipped_on) form.shipped_on = todayIso();
    },
);

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.title.trim()) {
        errors.value.title = 'Name what you want to build.';
        return;
    }
    saving.value = true;
    const payload = {
        title: form.title.trim(),
        area: form.area?.trim() || null,
        stage: form.stage,
        shipped_on: form.stage === 'shipped' ? form.shipped_on : null,
        details: form.details?.trim() || null,
    };
    try {
        const saved = props.item ? await api.roadmap.update(props.item.id, payload) : await api.roadmap.create(payload);
        toast.success(isEdit.value ? 'Roadmap item updated' : 'Added to the roadmap');
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
        :title="isEdit ? 'Edit roadmap item' : 'Add to the roadmap'"
        description="A feature or improvement for LovinceTracker, and when you plan to build it."
        :busy="saving"
    >
        <form id="roadmap-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="roadmap-title"
                v-model="form.title"
                label="What"
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
                <DatePicker
                    v-if="form.stage === 'shipped'"
                    id="roadmap-shipped"
                    v-model="form.shipped_on"
                    label="Shipped on"
                    :error="errors.shipped_on"
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
                isEdit ? 'Save changes' : 'Add item'
            }}</Button>
        </template>
    </Dialog>
</template>

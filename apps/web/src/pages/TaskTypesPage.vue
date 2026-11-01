<script setup lang="ts">
import { errorMessage, isApiError } from '@focus/api-client';
import { GOAL_TONES, LIMITS, type GoalTone } from '@focus/types';

const store = useTaskTypesStore();
const toast = useToast();

const nextTone = (): GoalTone => GOAL_TONES[store.types.length % GOAL_TONES.length] ?? 'blue';

const loading = ref(false);
const error = ref('');
const form = reactive({ name: '', tone: GOAL_TONES[0] as GoalTone });
const formError = ref('');
const creating = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        await store.load(true);
        form.tone = nextTone();
    } catch (e) {
        error.value = errorMessage(e, 'Could not load your task types.');
    } finally {
        loading.value = false;
    }
}

async function create(): Promise<void> {
    if (!form.name.trim()) {
        formError.value = 'Give the type a name.';
        return;
    }
    creating.value = true;
    formError.value = '';
    try {
        const type = await store.create({ name: form.name.trim(), tone: form.tone });
        toast.success(`“${type.name}” added`);
        form.name = '';
        form.tone = nextTone();
    } catch (e) {
        formError.value =
            isApiError(e) && e.isValidation
                ? (Object.values(e.fieldErrors())[0] ?? '')
                : errorMessage(e, 'Could not create the type.');
    } finally {
        creating.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Types"
            title-id="page-title"
            description="Your own kinds of task. They sit alongside General, Development and Integration in every task form."
        />

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else class="grid items-start gap-6 lg:grid-cols-3">
            <Card as="section" aria-labelledby="types-heading" class="group lg:col-span-2">
                <CardHeader
                    title="Your types"
                    title-id="types-heading"
                    icon="tag"
                    :subtitle="`${store.types.length} of ${LIMITS.taskType.max}`"
                />
                <Skeleton v-if="loading && !store.loaded" class="h-40" />
                <TaskTypeList v-else-if="store.types.length" @changed="load" />
                <EmptyState
                    v-else
                    icon="tag"
                    title="No types yet"
                    message="Add one, e.g. Study, Personal or Review, then pick it when you create a task."
                />
            </Card>

            <Card as="section" aria-labelledby="new-type-heading" class="group">
                <CardHeader title="New type" title-id="new-type-heading" icon="add" />
                <form class="flex flex-col gap-4" novalidate @submit.prevent="create">
                    <Input
                        id="new-type-name"
                        v-model="form.name"
                        label="Name"
                        :maxlength="LIMITS.taskType.name"
                        :error="formError"
                        autocomplete="off"
                    />
                    <TonePicker v-model="form.tone" label="Colour" />
                    <Button
                        type="submit"
                        icon-start="add"
                        :loading="creating"
                        :disabled="store.types.length >= LIMITS.taskType.max"
                        class="self-start"
                        >Add type</Button
                    >
                </form>
            </Card>
        </div>
    </div>
</template>

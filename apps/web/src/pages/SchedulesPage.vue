<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Schedule } from '@focus/types';

const route = useRoute();
const router = useRouter();
const schedules = ref<Schedule[]>([]);
const loading = ref(true);
const error = ref('');
const formOpen = ref(false);
const editing = ref<Schedule | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        schedules.value = await api.schedules.list();
    } catch (e) {
        error.value = errorMessage(e, 'Could not load your schedules.');
    } finally {
        loading.value = false;
    }
}

function open(schedule: Schedule | null): void {
    editing.value = schedule;
    formOpen.value = true;
}

watch(
    () => route.query.new,
    (value) => {
        if (!value) return;
        open(null);
        void router.replace({ query: {} });
    },
    { immediate: true },
);
onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Schedules"
            title-id="page-title"
            description="Routines you repeat, like your workday. Edit one in one place, then apply it to any day to fill its plan."
        />

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !schedules.length" class="grid gap-6 lg:grid-cols-2" aria-busy="true">
            <Skeleton v-for="n in 2" :key="n" class="h-64" />
        </div>
        <div v-else-if="schedules.length" class="grid items-start gap-6 lg:grid-cols-2">
            <ScheduleCard
                v-for="schedule in schedules"
                :key="schedule.id"
                :schedule="schedule"
                @edit="open(schedule)"
                @changed="load"
            />
        </div>
        <EmptyState
            v-else
            icon="calendar-clock"
            title="No schedules yet"
            message="Create a routine once, e.g. wake up, exercise, commute and work, then apply it to any day."
        >
            <Button icon-start="add" @click="open(null)">New schedule</Button>
        </EmptyState>

        <ScheduleFormDialog v-model:open="formOpen" :schedule="editing" @saved="load" />
    </div>
</template>

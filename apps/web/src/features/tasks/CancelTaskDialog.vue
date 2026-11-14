<script setup lang="ts">
import { LIMITS } from '@focus/types';
import { useCancelReasonPrompt } from './useCancelReason';

/** The one place the app asks why a task is being skipped; mounted once in the layout. */
const { pending, settle } = useCancelReasonPrompt();
const reason = ref('');
const quick = ['Woke up late', 'Did it at another time', 'Work ran over', 'Not feeling well'];

const open = computed({
    get: () => pending.value !== null,
    set: (value) => {
        if (!value) settle(undefined);
    },
});
watch(pending, (next) => {
    if (next) reason.value = '';
});

function submit(): void {
    settle(reason.value.trim() || null);
}
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Skip this task?"
        :description="pending ? `Say why you’re skipping “${pending.title}”, so you can plan around it.` : undefined"
        size="sm"
    >
        <form id="cancel-task-form" class="flex flex-col gap-4" @submit.prevent="submit">
            <Textarea
                id="cancel-task-reason"
                v-model="reason"
                label="Reason"
                optional
                :maxlength="LIMITS.task.cancelReason"
            />
            <div class="flex flex-wrap gap-2" role="group" aria-label="Common reasons">
                <Button
                    v-for="option in quick"
                    :key="option"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="reason = option"
                >
                    {{ option }}
                </Button>
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Keep it</Button>
            <Button type="submit" form="cancel-task-form" variant="destructive">Skip task</Button>
        </template>
    </Dialog>
</template>

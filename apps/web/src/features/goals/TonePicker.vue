<script setup lang="ts">
import { GOAL_TONES, type GoalTone } from '@focus/types';
import { goalToneLabels } from '@/lib/labels';
import { toneDot } from '@/lib/tones';

/** The colour picker shared by goals and task types. */
defineProps<{ label: string; description?: string }>();
const model = defineModel<GoalTone>({ required: true });
const options = GOAL_TONES.map((tone) => ({ value: tone, label: goalToneLabels[tone] }));
</script>

<template>
    <RadioGroup v-model="model" :label="label" :description="description" :options="options">
        <template #option="{ option }">
            <span :class="['size-3 rounded-full', toneDot[option.value]]" aria-hidden="true" />
        </template>
    </RadioGroup>
</template>

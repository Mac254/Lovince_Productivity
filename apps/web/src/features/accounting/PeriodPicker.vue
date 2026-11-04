<script setup lang="ts">
import { todayIso } from '@/lib/dates';
import { monthLabel, monthOf, type Period } from './money';

const period = defineModel<Period>({ required: true });

const label = computed(() => monthLabel(period.value));
const isCurrent = computed(() => period.value.from === monthOf(todayIso()).from);

function shift(offset: number): void {
    period.value = monthOf(period.value.from, offset);
}
</script>

<template>
    <div class="flex items-center gap-2" role="group" aria-label="Period">
        <IconButton icon="chevron-left" label="Previous month" size="sm" @click="shift(-1)" />
        <p class="min-w-36 text-center text-label font-semibold tabular" aria-live="polite">{{ label }}</p>
        <IconButton icon="chevron-right" label="Next month" size="sm" @click="shift(1)" />
        <Button v-if="!isCurrent" variant="ghost" size="sm" @click="period = monthOf(todayIso())">This month</Button>
    </div>
</template>

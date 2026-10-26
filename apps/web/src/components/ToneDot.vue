<script setup lang="ts">
import type { GoalTone } from '@focus/types';
import { toneDot } from '@/lib/tones';

defineProps<{ tone: GoalTone }>();
</script>

<template>
    <span aria-hidden="true" :class="['inline-block size-2 shrink-0 rounded-full', toneDot[tone]]" />
</template>

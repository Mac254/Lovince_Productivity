<script setup lang="ts">
import type { Transaction } from '@focus/types';

defineProps<{ busy: boolean }>();
const voiding = defineModel<Transaction | null>('voiding', { required: true });
const reversing = defineModel<Transaction | null>('reversing', { required: true });
const emit = defineEmits<{ void: []; reverse: [] }>();
</script>

<template>
    <ConfirmDialog
        :open="!!voiding"
        title="Void transaction?"
        :message="`“${voiding?.description}” will be treated as never having happened. It stays in your history, marked voided.`"
        confirm-label="Void"
        :loading="busy"
        @update:open="!$event && (voiding = null)"
        @confirm="emit('void')"
    />
    <ConfirmDialog
        :open="!!reversing"
        title="Reverse transaction?"
        :message="`An opposite transaction dated today will undo “${reversing?.description}”. Both stay in your history and earlier months are unchanged.`"
        confirm-label="Reverse"
        :loading="busy"
        @update:open="!$event && (reversing = null)"
        @confirm="emit('reverse')"
    />
</template>

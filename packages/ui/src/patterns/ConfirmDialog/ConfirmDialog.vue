<script setup lang="ts">
import Button from '../../components/Button/Button.vue';
import Dialog from '../../components/Dialog/Dialog.vue';

/** Confirmation for irreversible actions. Cancel is always offered and listed first for focus. */
withDefaults(defineProps<{ title: string; message: string; confirmLabel?: string; loading?: boolean }>(), {
    confirmLabel: 'Delete',
});
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open" :title="title" size="sm">
        <p class="text-body text-fg-muted">{{ message }}</p>
        <template #footer>
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button variant="destructive" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</Button>
        </template>
    </Dialog>
</template>

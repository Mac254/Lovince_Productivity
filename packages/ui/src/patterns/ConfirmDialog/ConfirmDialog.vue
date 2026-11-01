<script setup lang="ts">
import { useId } from 'vue';
import Button from '../../components/Button/Button.vue';
import Dialog from '../../components/Dialog/Dialog.vue';

/** Confirmation for irreversible actions, as an alertdialog that opens on Cancel. */
withDefaults(defineProps<{ title: string; message: string; confirmLabel?: string; loading?: boolean }>(), {
    confirmLabel: 'Delete',
});
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [] }>();
const cancelId = useId();
</script>

<template>
    <Dialog v-model:open="open" :title="title" size="sm" alert :initial-focus="cancelId">
        <p class="text-body text-fg-muted">{{ message }}</p>
        <template #footer>
            <Button :id="cancelId" variant="outline" @click="open = false">Cancel</Button>
            <Button variant="destructive" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</Button>
        </template>
    </Dialog>
</template>

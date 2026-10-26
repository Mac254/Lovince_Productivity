<script setup lang="ts">
import Icon from '../../primitives/Icon/Icon.vue';
import Button from '../../components/Button/Button.vue';

/** What failed and a retry control. */
defineProps<{ message: string }>();
const emit = defineEmits<{ retry: [] }>();
</script>

<template>
    <div
        role="alert"
        class="flex flex-col items-center gap-4 rounded-lg border border-border bg-surface px-6 py-12 text-center"
    >
        <Icon name="warning" size="lg" class="text-highlight-fg" />
        <p class="max-w-form text-body">{{ message }}</p>
        <Button variant="outline" size="sm" icon-start="retry" @click="emit('retry')">Try again</Button>
    </div>
</template>

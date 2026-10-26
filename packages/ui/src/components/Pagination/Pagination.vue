<script setup lang="ts">
import IconButton from '../IconButton/IconButton.vue';

defineProps<{ lastPage: number }>();
const page = defineModel<number>('page', { required: true });
</script>

<template>
    <nav v-if="lastPage > 1" class="flex items-center justify-center gap-4" aria-label="Pagination">
        <IconButton icon="chevron-left" label="Previous page" :disabled="page <= 1" @click="page--" />
        <span class="text-label font-semibold tabular" aria-live="polite">Page {{ page }} of {{ lastPage }}</span>
        <IconButton icon="chevron-right" label="Next page" :disabled="page >= lastPage" @click="page++" />
    </nav>
</template>

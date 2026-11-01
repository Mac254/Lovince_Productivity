<script setup lang="ts">
import { LvPaginationNext, LvPaginationPrevious, LvPaginationRoot } from '@lovince/ui-vue';
import IconButton from '../IconButton/IconButton.vue';

defineProps<{ lastPage: number }>();
const page = defineModel<number>('page', { required: true });
</script>

<template>
    <LvPaginationRoot
        v-if="lastPage > 1"
        v-model:page="page"
        :count="lastPage"
        class="flex items-center justify-center gap-4"
    >
        <LvPaginationPrevious as-child>
            <IconButton icon="chevron-left" label="Previous page" :disabled="page <= 1" />
        </LvPaginationPrevious>
        <span class="text-label font-semibold tabular" aria-live="polite">Page {{ page }} of {{ lastPage }}</span>
        <LvPaginationNext as-child>
            <IconButton icon="chevron-right" label="Next page" :disabled="page >= lastPage" />
        </LvPaginationNext>
    </LvPaginationRoot>
</template>

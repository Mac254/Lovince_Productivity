<script setup lang="ts">
import {
    LvPaginationEllipsis,
    LvPaginationNext,
    LvPaginationPage,
    LvPaginationPrevious,
    LvPaginationRoot,
} from '@lovince/ui-vue';
import { computed } from 'vue';
import { buttonVariants, controlSquares, interactive } from '../Button/variants';
import IconButton from '../IconButton/IconButton.vue';

/**
 * Page controls: previous, numbered pages with gaps, next. With `total` and `perPage` it also
 * says which items are showing. On phones only the page count sits between the arrows.
 */
const props = defineProps<{ lastPage: number; total?: number; perPage?: number; noun?: string }>();
const page = defineModel<number>('page', { required: true });

const range = computed(() => {
    if (props.total === undefined || !props.perPage) return null;
    const from = Math.min((page.value - 1) * props.perPage + 1, props.total);
    const to = Math.min(page.value * props.perPage, props.total);
    return { from, to };
});
const format = (value: number) => value.toLocaleString();
</script>

<template>
    <LvPaginationRoot
        v-if="lastPage > 1"
        v-slot="{ entries }"
        v-model:page="page"
        :count="lastPage"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
    >
        <p v-if="range" class="text-caption text-fg-muted tabular" aria-live="polite">
            Showing {{ format(range.from) }}–{{ format(range.to) }} of {{ format(total!) }} {{ noun ?? 'items' }}
        </p>
        <div class="flex items-center gap-1" :class="!range && 'sm:mx-auto'">
            <LvPaginationPrevious as-child>
                <IconButton icon="chevron-left" label="Previous page" variant="ghost" :disabled="page <= 1" />
            </LvPaginationPrevious>
            <span class="px-2 text-label font-semibold tabular sm:hidden" :aria-live="range ? undefined : 'polite'"
                >Page {{ page }} of {{ lastPage }}</span
            >
            <template v-for="entry in entries" :key="entry.type === 'page' ? entry.page : entry.key">
                <LvPaginationPage v-if="entry.type === 'page'" :page="entry.page" as-child>
                    <button
                        type="button"
                        class="max-sm:hidden"
                        :class="[
                            interactive,
                            controlSquares.md,
                            'text-label tabular',
                            buttonVariants[entry.page === page ? 'primary' : 'ghost'],
                        ]"
                    >
                        {{ entry.page }}
                    </button>
                </LvPaginationPage>
                <LvPaginationEllipsis
                    v-else
                    class="inline-flex size-(--control-md) items-center justify-center text-fg-muted max-sm:hidden"
                />
            </template>
            <LvPaginationNext as-child>
                <IconButton icon="chevron-right" label="Next page" variant="ghost" :disabled="page >= lastPage" />
            </LvPaginationNext>
        </div>
    </LvPaginationRoot>
</template>

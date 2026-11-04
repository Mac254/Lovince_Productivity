<script setup lang="ts">
import { LvSearchInputClear, LvSearchInputInput, LvSearchInputRoot } from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import { useFieldAttrs } from '../../composables/useFieldAttrs';

/** Search box on Lovince: Escape or the clear button empties it. */
defineOptions({ inheritAttrs: false });
const { root, control } = useFieldAttrs();
defineProps<{ id: string; label: string; placeholder?: string }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <LvSearchInputRoot v-slot="{ value }" v-bind="root()" v-model="model" class="relative">
        <Icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-fg-muted" />
        <LvSearchInputInput
            :id="id"
            v-bind="control()"
            :label="label"
            :placeholder="placeholder"
            class="h-(--control-md) w-full rounded-full border border-border-control bg-surface pr-12 pl-12 text-body placeholder:text-fg-subtle transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:border-highlight focus-visible:outline-none"
        />
        <LvSearchInputClear
            v-if="value"
            class="absolute top-1/2 right-2 inline-flex size-(--control-sm) -translate-y-1/2 items-center justify-center rounded-full text-fg-muted transition-colors duration-(--duration-instant) hover:bg-surface-raised hover:text-fg"
        >
            <Icon name="close" />
        </LvSearchInputClear>
    </LvSearchInputRoot>
</template>

<style scoped>
/* The clear button replaces the browser's own. */
input::-webkit-search-cancel-button {
    appearance: none;
}
</style>

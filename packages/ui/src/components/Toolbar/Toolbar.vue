<script setup lang="ts">
import { LvToolbarRoot } from '@lovince/ui-vue';
import { computed, onMounted, ref } from 'vue';

/**
 * A row of controls on Lovince UI's toolbar: one tab stop, arrow keys and Home/End move between items.
 * Lovince collects items from the root's DOM when they mount, so they render after the root and
 * remount when `disabled` changes, keeping the tab stop on an item that is enabled.
 */
const props = defineProps<{ label: string; disabled?: boolean }>();

const mounted = ref(false);
onMounted(() => (mounted.value = true));
const generation = computed(() => (mounted.value ? [props.disabled ? 'disabled' : 'enabled'] : []));
</script>

<template>
    <LvToolbarRoot :label="label" :disabled="disabled" class="flex flex-wrap items-center gap-1">
        <template v-for="state in generation" :key="state">
            <slot />
        </template>
    </LvToolbarRoot>
</template>

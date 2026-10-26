<script setup lang="ts">
import Icon from '../../primitives/Icon/Icon.vue';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string; label: string; placeholder?: string }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <label :for="id" class="sr-only">{{ label }}</label>
        <Icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-fg-muted" />
        <input
            :id="id"
            v-model="model"
            v-bind="$attrs"
            type="search"
            :placeholder="placeholder"
            class="h-(--control-md) w-full rounded-full border border-border-control bg-surface pr-4 pl-12 text-body placeholder:text-fg-subtle transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:border-highlight focus-visible:outline-none"
        />
    </div>
</template>

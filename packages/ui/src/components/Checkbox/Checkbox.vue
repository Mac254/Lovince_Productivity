<script setup lang="ts">
defineProps<{ id: string; label: string }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label
        :for="id"
        class="inline-flex min-h-(--control-sm) cursor-pointer items-center gap-3 text-label font-semibold text-fg-muted"
    >
        <input :id="id" v-model="model" type="checkbox" class="size-(--icon-md) rounded-sm accent-accent" />
        {{ label }}
    </label>
</template>

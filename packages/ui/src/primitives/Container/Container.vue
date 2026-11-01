<script setup lang="ts">
import { LvBox } from '@lovince/ui-vue';

withDefaults(defineProps<{ as?: string; size?: 'app' | 'form' | 'dialog' }>(), { as: 'div', size: 'app' });
</script>

<template>
    <LvBox
        :as="as"
        :class="[
            'mx-auto w-full px-4 sm:px-6 lg:px-8',
            { app: 'max-w-app', form: 'max-w-form', dialog: 'max-w-dialog' }[size],
        ]"
    >
        <slot />
    </LvBox>
</template>

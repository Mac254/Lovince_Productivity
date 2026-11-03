<script setup lang="ts">
import { loadFont, type FontKey } from './fonts';

const props = defineProps<{ font: FontKey }>();

const el = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!el.value || typeof IntersectionObserver === 'undefined') return void loadFont(props.font);
    observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        void loadFont(props.font);
        observer?.disconnect();
    });
    observer.observe(el.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <span ref="el" :data-font="font" class="flex w-full flex-col gap-1 text-fg">
        <span class="font-display text-title font-semibold tracking-tight">Plan the day</span>
        <span class="font-sans text-label font-medium text-fg-muted"
            >Finish the work that matters, one focused block at a time.</span
        >
    </span>
</template>

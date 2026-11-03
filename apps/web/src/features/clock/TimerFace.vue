<script setup lang="ts">
import type { TimerFace } from './wallpapers';

const props = defineProps<{
    face: TimerFace;
    progress: number;
    display: string;
    status: string;
    task?: string | null;
    size: number;
}>();

const label = computed(() => `${props.status}, ${props.display} remaining`);
const fill = computed(() => Math.min(1, Math.max(0, props.progress)));
</script>

<template>
    <TimerRing v-if="face === 'ring'" :progress="progress" :size="size" :label="label" data-face="ring">
        <p class="text-label font-semibold tracking-wide uppercase opacity-80">{{ status }}</p>
        <p class="timer-digits face-ring font-display font-semibold" aria-hidden="true">
            <RollingDigits :text="display" />
        </p>
        <p v-if="task" class="mt-1 max-w-56 truncate text-label opacity-90">{{ task }}</p>
    </TimerRing>

    <div v-else :data-face="face" class="flex w-full flex-col items-center gap-4 text-center">
        <p class="sr-only">{{ label }}</p>
        <p
            :class="[
                'text-label font-semibold tracking-wide uppercase',
                face === 'minimal' ? 'opacity-60' : 'opacity-80',
            ]"
            aria-hidden="true"
        >
            {{ status }}
        </p>

        <p v-if="face === 'flip'" class="timer-digits face-flip font-display font-semibold">
            <FlipDigits :text="display" />
        </p>
        <p
            v-else
            :class="['timer-digits font-display', face === 'digital' ? 'face-digital font-semibold' : 'face-minimal']"
            aria-hidden="true"
        >
            <RollingDigits :text="display" />
        </p>

        <div
            :class="['progress w-full overflow-hidden rounded-full', face === 'minimal' ? 'h-px' : 'h-1']"
            aria-hidden="true"
        >
            <div class="progress-fill h-full" :style="{ transform: `scaleX(${fill})` }" />
        </div>
        <p v-if="task" class="max-w-full truncate text-label opacity-90">{{ task }}</p>
    </div>
</template>

<style scoped>
.timer-digits {
    line-height: 1;
}

.face-ring {
    font-size: clamp(3rem, 9vw, 5.5rem);
}

.face-digital {
    font-size: clamp(4.5rem, 22vw, 13rem);
    letter-spacing: var(--tracking-tight);
    line-height: 0.9;
}

.face-minimal {
    font-size: clamp(3rem, 12vw, 6rem);
    font-weight: 300;
}

.face-flip {
    font-size: clamp(3rem, 15vw, 8rem);
}

.progress {
    max-width: 28rem;
    background: color-mix(in oklab, currentColor 14%, transparent);
}

.progress-fill {
    background: var(--highlight);
    transform-origin: left;
    transition: transform var(--duration-slow) linear;
}
</style>

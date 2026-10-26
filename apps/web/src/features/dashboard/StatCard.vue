<script setup lang="ts">
import { useAnimatedNumber } from '@focus/motion';
import { Card, Icon, type IconMotion, type IconName } from '@focus/ui';
import { computed } from 'vue';

/** KPI tile: label, one number, and a supporting line. Values animate in; the icon reacts on hover. */
const props = withDefaults(
    defineProps<{
        label: string;
        value: number | null;
        icon: IconName;
        motion?: IconMotion;
        suffix?: string;
        hint?: string;
        emphasis?: boolean;
    }>(),
    { motion: 'pop' },
);

const animated = useAnimatedNumber(() => props.value ?? 0);
const display = computed(() => (props.value === null ? '—' : `${animated.value}${props.suffix ?? ''}`));
</script>

<template>
    <Card as="div" :tone="emphasis ? 'highlight' : 'default'" class="group gap-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-label font-semibold" :class="emphasis ? 'text-highlight-ink' : 'text-fg-muted'">
                {{ label }}
            </p>
            <span
                :class="[
                    'inline-flex size-(--control-md) items-center justify-center rounded-md',
                    emphasis ? 'bg-highlight-ink text-highlight' : 'bg-surface-raised text-highlight-fg',
                ]"
            >
                <Icon :name="icon" size="md" :motion="motion" class="motion-enter" />
            </span>
        </div>
        <div>
            <p class="font-display text-display-sm font-semibold tracking-tight">
                <span aria-hidden="true">{{ display }}</span>
                <span class="sr-only">{{ value === null ? 'Not available' : `${value}${suffix ?? ''}` }}</span>
            </p>
            <p v-if="hint" class="mt-1 text-label" :class="emphasis ? 'text-highlight-ink' : 'text-fg-muted'">
                {{ hint }}
            </p>
        </div>
    </Card>
</template>

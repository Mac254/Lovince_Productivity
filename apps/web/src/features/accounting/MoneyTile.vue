<script setup lang="ts">
import type { IconName, IconMotion } from '@focus/ui';

/** A money figure with its label and one supporting line. */
withDefaults(
    defineProps<{ label: string; value: string; icon: IconName; motion?: IconMotion; hint?: string; emphasis?: boolean }>(),
    { motion: 'pop' },
);
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
                <Icon :name="icon" size="md" :motion="motion" />
            </span>
        </div>
        <div>
            <p class="font-display text-title font-semibold tracking-tight tabular">{{ value }}</p>
            <p v-if="hint" class="mt-1 text-label" :class="emphasis ? 'text-highlight-ink' : 'text-fg-muted'">
                {{ hint }}
            </p>
        </div>
    </Card>
</template>

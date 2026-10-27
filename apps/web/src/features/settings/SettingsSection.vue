<script setup lang="ts">
import type { IconName } from '@focus/ui';
import { Card, CardHeader } from '@focus/ui';

/** A titled group of settings rows, laid out as a key–value list. */
defineProps<{ id: string; title: string; icon: IconName; description?: string }>();
</script>

<template>
    <Card :id="id" as="section" :aria-labelledby="`${id}-heading`" class="group scroll-mt-(--topbar-height) gap-2">
        <CardHeader :title="title" :title-id="`${id}-heading`" :icon="icon" :subtitle="description" />
        <dl class="flex flex-col divide-y divide-border">
            <slot />
        </dl>
    </Card>
</template>

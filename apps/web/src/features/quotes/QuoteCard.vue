<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Quote } from '@focus/types';
import { Card, Icon, IconButton, Text } from '@focus/ui';
import { ref, watch } from 'vue';

/** Works without quotes: the card falls back to a short line and the dashboard is unaffected. */
const props = defineProps<{ initial: Quote | null }>();

const quote = ref<Quote | null>(props.initial);
const loading = ref(false);
const failed = ref(false);

watch(
    () => props.initial,
    (value) => {
        if (!quote.value) quote.value = value;
    },
);

async function next(): Promise<void> {
    loading.value = true;
    failed.value = false;
    try {
        quote.value = (await api.quotes.random(quote.value?.id)) ?? quote.value;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Card as="section" tone="accent" aria-labelledby="quote-heading" class="gap-4">
        <div class="flex items-start justify-between gap-3">
            <Icon name="quote" size="lg" class="motion-enter" />
            <h2 id="quote-heading" class="sr-only">Quote of the moment</h2>
            <IconButton
                icon="shuffle"
                label="Show another quote"
                variant="primary"
                size="sm"
                motion="spin"
                :disabled="loading"
                @click="next"
            />
        </div>
        <figure v-if="quote" class="flex flex-1 flex-col gap-4" aria-live="polite">
            <blockquote class="font-display text-title font-medium text-balance">
                <p>{{ quote.text }}</p>
            </blockquote>
            <Text as="figcaption" role="label" weight="semibold" class="mt-auto"
                >— {{ quote.author ?? 'Unknown' }}</Text
            >
        </figure>
        <p v-else class="font-display text-title font-medium">Make today count.</p>
        <Text v-if="failed" role="label">Couldn’t load a new quote right now.</Text>
    </Card>
</template>

<script setup lang="ts">
import { Button, Card, Heading, Icon, Text } from '@focus/ui';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Wordmark from '@/components/Wordmark.vue';
import { clearReloadGuard } from '@/lib/chunkReload';

const route = useRoute();
const router = useRouter();
const retrying = ref(false);

const reason = computed(() => String(route.query.reason ?? ''));
const offline = computed(() => ['network', 'timeout'].includes(reason.value));
const updated = computed(() => reason.value === 'update');
const copy = computed(() =>
    updated.value
        ? {
              title: 'Focus was updated',
              message: 'A newer version of the app is available. Reload to continue — nothing you saved is lost.',
          }
        : offline.value
          ? {
                title: 'Can’t reach the server',
                message:
                    'Focus couldn’t connect to its API. Check that the server is running and your connection is up.',
            }
          : {
                title: 'The server ran into a problem',
                message:
                    'Focus is running, but the API returned an error while loading your session. Your data is safe.',
            },
);

/** Only same-app paths are followed. */
function target(): string {
    const redirect = route.query.redirect;
    return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/';
}

async function retry(): Promise<void> {
    retrying.value = true;
    // A failed module import is cached by the browser, so only a real reload can recover it.
    if (updated.value) {
        clearReloadGuard();
        window.location.assign(target());
        return;
    }
    try {
        await router.replace(target());
    } catch {
        // The guard routes failures back here with an explanation.
    } finally {
        retrying.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-dvh items-center justify-center px-4 py-12">
        <div class="flex w-full max-w-form flex-col gap-8">
            <p class="text-center text-display-sm"><Wordmark /></p>
            <Card as="section" padding="lg" aria-labelledby="unavailable-title" role="alert">
                <template #actions>
                    <span
                        class="inline-flex size-(--control-md) items-center justify-center rounded-full bg-highlight text-highlight-ink"
                    >
                        <Icon :name="offline ? 'offline' : 'server-error'" />
                    </span>
                </template>
                <Heading id="unavailable-title" :level="1" role="heading" class="pr-12">{{ copy.title }}</Heading>
                <Text tone="muted" class="mt-2">{{ copy.message }}</Text>
                <Button icon-start="retry" :loading="retrying" class="mt-8 w-full" @click="retry">{{
                    updated ? 'Reload' : 'Try again'
                }}</Button>
            </Card>
            <Text v-if="!offline && !updated" role="label" tone="muted" class="text-center">
                Running it yourself? Run
                <code class="rounded-sm bg-surface px-2 py-1 whitespace-nowrap text-fg">php artisan focus:doctor</code>
                in <code class="rounded-sm bg-surface px-2 py-1 whitespace-nowrap text-fg">apps/api</code> to see what’s
                wrong.
            </Text>
        </div>
    </main>
</template>

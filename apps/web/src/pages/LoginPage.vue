<script setup lang="ts">
import { errorMessage, isApiError } from '@focus/api-client';
import { Button, Card, Checkbox, Heading, Icon, Input, Text } from '@focus/ui';
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Wordmark from '@/components/Wordmark.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '', remember: true });
const errors = ref<Record<string, string>>({});
const formError = ref<string | null>(null);
const submitting = ref(false);

/** Only same-app paths are followed after sign-in. */
function safeRedirect(): string {
    const target = route.query.redirect;
    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/';
}

async function submit(): Promise<void> {
    errors.value = {};
    formError.value = null;
    if (!form.email.trim()) errors.value.email = 'Enter your email address.';
    if (!form.password) errors.value.password = 'Enter your password.';
    if (Object.keys(errors.value).length) return;

    submitting.value = true;
    try {
        await auth.login({ email: form.email.trim(), password: form.password, remember: form.remember });
        form.password = '';
        await router.replace(safeRedirect());
    } catch (e) {
        formError.value =
            isApiError(e) && e.kind === 'forbidden'
                ? 'Sign in from the Focus web app.'
                : errorMessage(e, 'Sign-in failed. Please try again.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-dvh items-center justify-center px-4 py-12">
        <div class="flex w-full max-w-form flex-col gap-8">
            <p class="text-center text-display-sm sm:text-display-md"><Wordmark /></p>
            <Card as="section" padding="lg" aria-labelledby="login-title">
                <template #actions>
                    <span
                        class="inline-flex size-(--control-md) items-center justify-center rounded-full bg-highlight text-highlight-ink"
                    >
                        <Icon name="lock" />
                    </span>
                </template>
                <Heading id="login-title" :level="1" role="heading">Welcome back</Heading>
                <Text tone="muted" class="mt-1">Sign in to your private workspace.</Text>

                <p
                    v-if="formError"
                    role="alert"
                    class="mt-6 rounded-md bg-highlight px-4 py-3 text-label font-semibold text-highlight-ink"
                >
                    {{ formError }}
                </p>

                <form class="mt-8 flex flex-col gap-6" novalidate @submit.prevent="submit">
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        label="Email"
                        autocomplete="username"
                        inputmode="email"
                        required
                        :error="errors.email"
                    />
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        label="Password"
                        autocomplete="current-password"
                        required
                        :error="errors.password"
                    />
                    <Checkbox
                        id="remember"
                        v-model="form.remember"
                        label="Keep me signed in on this device until I sign out"
                    />
                    <Button type="submit" :loading="submitting" icon-end="arrow-right" class="w-full">Sign in</Button>
                </form>
            </Card>
            <Text role="label" tone="muted" class="text-center">
                No account yet? Create one on the server with
                <code class="rounded-sm bg-surface px-2 py-1 whitespace-nowrap text-fg"
                    >php artisan focus:create-user</code
                >.
            </Text>
        </div>
    </main>
</template>

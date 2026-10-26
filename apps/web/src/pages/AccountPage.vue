<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { Avatar, Button, Card, CardHeader, Input, PageHeader, Text, useToast } from '@focus/ui';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { formatDate } from '@/lib/dates';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const signingOut = ref(false);

const sessionLine = computed(() => {
    const session = auth.session;
    if (!session) return '';
    const until = formatDate(session.expires_at, { dateStyle: 'medium', timeStyle: 'short' });
    return session.remembered
        ? `Signed in until ${until}. Each visit renews it for another ${session.remember_days} days.`
        : `Signed in until ${until} if idle — this lasts ${session.idle_timeout_minutes} minutes from your last action.`;
});

async function signOut(): Promise<void> {
    signingOut.value = true;
    try {
        await auth.logout();
    } finally {
        signingOut.value = false;
        await router.replace({ name: 'login' });
    }
}

onMounted(() => void auth.refresh().catch(() => undefined));
const toast = useToast();

const name = ref(auth.user?.name ?? '');
const nameError = ref<string>();
const savingName = ref(false);

const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const password = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref<Record<string, string>>({});
const savingPassword = ref(false);

async function saveName(): Promise<void> {
    nameError.value = name.value.trim() ? undefined : 'Enter your name.';
    if (nameError.value) return;
    savingName.value = true;
    try {
        auth.user = await api.profile.update({ name: name.value.trim() });
        toast.success('Name updated');
    } catch (e) {
        if (isApiError(e) && e.isValidation) nameError.value = e.fieldErrors().name;
        else toast.error(errorMessage(e));
    } finally {
        savingName.value = false;
    }
}

async function upload(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        toast.error('Choose an image under 2 MB.');
        return;
    }
    uploading.value = true;
    try {
        auth.user = await api.profile.uploadAvatar(file);
        toast.success('Photo updated');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not upload the photo.'));
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

async function removePhoto(): Promise<void> {
    try {
        auth.user = await api.profile.removeAvatar();
        toast.success('Photo removed');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function changePassword(): Promise<void> {
    passwordErrors.value = {};
    if (password.password !== password.password_confirmation) {
        passwordErrors.value.password_confirmation = 'The passwords don’t match.';
        return;
    }
    savingPassword.value = true;
    try {
        await api.profile.changePassword({ ...password });
        Object.assign(password, { current_password: '', password: '', password_confirmation: '' });
        toast.success('Password changed');
    } catch (e) {
        if (isApiError(e) && e.isValidation) passwordErrors.value = e.fieldErrors();
        else toast.error(errorMessage(e));
    } finally {
        savingPassword.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader title="Account" title-id="page-title" description="Your name, photo and password." />

        <div class="grid gap-6 lg:grid-cols-2">
            <Card as="section" aria-labelledby="profile-heading" class="group gap-6">
                <CardHeader title="Profile" title-id="profile-heading" icon="user" />
                <div class="flex flex-wrap items-center gap-4">
                    <Avatar :name="auth.user?.name ?? ''" :src="auth.user?.avatar_url" size="xl" />
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                icon-start="camera"
                                :loading="uploading"
                                @click="fileInput?.click()"
                                >Upload photo</Button
                            >
                            <Button
                                v-if="auth.user?.avatar_url"
                                variant="ghost"
                                size="sm"
                                icon-start="trash"
                                @click="removePhoto"
                                >Remove</Button
                            >
                        </div>
                        <Text role="caption" tone="muted">JPG, PNG or WebP, up to 2 MB. Only you can see it.</Text>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            aria-label="Choose a profile photo"
                            @change="upload"
                        />
                    </div>
                </div>
                <form class="flex flex-col gap-4" novalidate @submit.prevent="saveName">
                    <Input
                        id="profile-name"
                        v-model="name"
                        label="Name"
                        autocomplete="name"
                        maxlength="120"
                        :error="nameError"
                    />
                    <Input
                        id="profile-email"
                        :model-value="auth.user?.email ?? ''"
                        label="Email"
                        readonly
                        hint="Email can only be changed on the server."
                    />
                    <Button type="submit" class="self-start" :loading="savingName" icon-start="save"
                        >Save profile</Button
                    >
                </form>
            </Card>

            <Card as="section" aria-labelledby="password-heading" class="group gap-6">
                <CardHeader
                    title="Password"
                    title-id="password-heading"
                    icon="key"
                    subtitle="At least 12 characters with upper and lower case letters and a number."
                />
                <form class="flex flex-col gap-4" novalidate @submit.prevent="changePassword">
                    <Input
                        id="current-password"
                        v-model="password.current_password"
                        type="password"
                        label="Current password"
                        autocomplete="current-password"
                        :error="passwordErrors.current_password"
                    />
                    <Input
                        id="new-password"
                        v-model="password.password"
                        type="password"
                        label="New password"
                        autocomplete="new-password"
                        :error="passwordErrors.password"
                    />
                    <Input
                        id="confirm-password"
                        v-model="password.password_confirmation"
                        type="password"
                        label="Confirm new password"
                        autocomplete="new-password"
                        :error="passwordErrors.password_confirmation"
                    />
                    <Button type="submit" class="self-start" :loading="savingPassword" icon-start="key"
                        >Change password</Button
                    >
                </form>
            </Card>
        </div>

        <Card as="section" aria-labelledby="device-heading" class="group gap-4">
            <CardHeader title="This device" title-id="device-heading" icon="lock" />
            <Text>{{ sessionLine }}</Text>
            <Text role="label" tone="muted">
                Your sign-in is kept in a secure, HTTP-only cookie that scripts can’t read. Signing out ends it
                immediately and invalidates the “keep me signed in” cookie.
            </Text>
            <Button variant="outline" icon-start="logout" class="self-start" :loading="signingOut" @click="signOut"
                >Sign out of this device</Button
            >
        </Card>
    </div>
</template>

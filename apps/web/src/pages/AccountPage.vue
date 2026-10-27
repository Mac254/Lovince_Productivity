<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS } from '@focus/types';
import { Avatar, Button, ChipGroup, ConfirmDialog, Dialog, Input, PageHeader, Switch, Text, useToast } from '@focus/ui';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { defaultClockPrefs, loadPrefs, savePrefs } from '@/features/clock/wallpapers';
import SettingRow from '@/features/settings/SettingRow.vue';
import SettingsSection from '@/features/settings/SettingsSection.vue';
import { formatDate } from '@/lib/dates';
import { useAuthStore } from '@/stores/auth';
import { usePreferencesStore, type StartPage, type ThemeChoice } from '@/stores/preferences';
import { useTimerStore } from '@/stores/timer';

const auth = useAuthStore();
const preferences = usePreferencesStore();
const timer = useTimerStore();
const router = useRouter();
const toast = useToast();

const sections = [
    { id: 'profile', label: 'Profile' },
    { id: 'general', label: 'General' },
    { id: 'appearance', label: 'Appearance' },
    { id: 'clock', label: 'Clock & timer' },
    { id: 'security', label: 'Security' },
    { id: 'reset', label: 'Reset' },
];

const themeOptions: { value: ThemeChoice; label: string }[] = [
    { value: 'dark', label: 'Dark' },
    { value: 'light', label: 'Light' },
    { value: 'system', label: 'System' },
];
const startPages: { value: StartPage; label: string }[] = [
    { value: 'dashboard', label: 'Dashboard' },
    { value: 'today', label: 'Today' },
    { value: 'focus', label: 'Focus clock' },
];
const timeFormats = [
    { value: '24', label: '24-hour' },
    { value: '12', label: '12-hour' },
];

const clock = reactive(loadPrefs());
const timeFormat = computed({
    get: () => (clock.hour12 ? '12' : '24'),
    set: (value: string) => (clock.hour12 = value === '12'),
});
watch(clock, savePrefs, { deep: true });

const notificationsSupported = typeof Notification !== 'undefined';
const notificationPermission = ref(notificationsSupported ? Notification.permission : 'denied');
const notificationStatus = computed(() => {
    if (!notificationsSupported) return 'Not available in this browser';
    if (notificationPermission.value === 'granted') return 'On';
    if (notificationPermission.value === 'denied') return 'Blocked in your browser’s site settings';
    return 'Off';
});

async function allowNotifications(): Promise<void> {
    try {
        notificationPermission.value = await Notification.requestPermission();
        if (notificationPermission.value === 'granted') toast.success('Notifications on');
    } catch {
        toast.error('This browser can’t show notifications.');
    }
}

const resetOpen = ref(false);
function resetPreferences(): void {
    preferences.reset();
    Object.assign(clock, defaultClockPrefs);
    timer.sound = true;
    timer.autoBreak = false;
    resetOpen.value = false;
    toast.success('Preferences reset on this device');
}

const session = computed(() => {
    const current = auth.session;
    if (!current) return null;
    const until = formatDate(current.expires_at, { dateStyle: 'medium', timeStyle: 'short' });
    return {
        value: current.remembered ? 'Kept signed in' : 'Signed in for this visit',
        detail: current.remembered
            ? `Until ${until} · each visit renews it for ${current.remember_days} days`
            : `Until ${until} if idle for ${current.idle_timeout_minutes} minutes`,
    };
});

const signingOut = ref(false);
async function signOut(): Promise<void> {
    signingOut.value = true;
    try {
        await auth.logout();
    } finally {
        signingOut.value = false;
        await router.replace({ name: 'login' });
    }
}

const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

async function upload(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (fileInput.value) fileInput.value.value = '';
    if (!file) return;
    if (file.size > LIMITS.avatarKb * 1024) {
        toast.error(`Choose an image under ${Math.round(LIMITS.avatarKb / 1024)} MB.`);
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

const nameOpen = ref(false);
const name = ref('');
const nameError = ref<string>();
const savingName = ref(false);

function editName(): void {
    name.value = auth.user?.name ?? '';
    nameError.value = undefined;
    nameOpen.value = true;
}

async function saveName(): Promise<void> {
    nameError.value = name.value.trim() ? undefined : 'Enter your name.';
    if (nameError.value) return;
    savingName.value = true;
    try {
        auth.user = await api.profile.update({ name: name.value.trim() });
        nameOpen.value = false;
        toast.success('Name updated');
    } catch (e) {
        if (isApiError(e) && e.isValidation) nameError.value = e.fieldErrors().name;
        else toast.error(errorMessage(e));
    } finally {
        savingName.value = false;
    }
}

const passwordOpen = ref(false);
const password = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref<Record<string, string>>({});
const savingPassword = ref(false);

function editPassword(): void {
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' });
    passwordErrors.value = {};
    passwordOpen.value = true;
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
        passwordOpen.value = false;
        toast.success('Password changed');
    } catch (e) {
        if (isApiError(e) && e.isValidation) passwordErrors.value = e.fieldErrors();
        else toast.error(errorMessage(e));
    } finally {
        savingPassword.value = false;
    }
}

onMounted(() => void auth.refresh().catch(() => undefined));
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Settings"
            title-id="page-title"
            description="Your profile, how Focus looks, and how you sign in."
        />

        <div class="grid gap-6 lg:grid-cols-4 lg:items-start">
            <nav
                aria-label="Settings sections"
                class="hidden lg:sticky lg:top-(--topbar-height) lg:flex lg:flex-col lg:gap-1 lg:pt-6"
            >
                <a
                    v-for="section in sections"
                    :key="section.id"
                    :href="`#${section.id}`"
                    class="rounded-md px-3 py-2 text-label font-semibold text-fg-muted transition-colors hover:bg-surface hover:text-fg"
                    >{{ section.label }}</a
                >
            </nav>

            <div class="flex flex-col gap-6 lg:col-span-3">
                <SettingsSection id="profile" title="Profile" icon="user" description="Only you can see these.">
                    <SettingRow label="Photo" description="JPG, PNG or WebP, up to 2 MB.">
                        <Avatar :name="auth.user?.name ?? ''" :src="auth.user?.avatar_url" size="lg" />
                        <template #action>
                            <Button
                                v-if="auth.user?.avatar_url"
                                variant="ghost"
                                size="sm"
                                icon-start="trash"
                                @click="removePhoto"
                                >Remove</Button
                            >
                            <Button
                                variant="outline"
                                size="sm"
                                icon-start="camera"
                                :loading="uploading"
                                @click="fileInput?.click()"
                                >{{ auth.user?.avatar_url ? 'Replace' : 'Upload' }}</Button
                            >
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                aria-label="Choose a profile photo"
                                @change="upload"
                            />
                        </template>
                    </SettingRow>
                    <SettingRow label="Name">
                        <Text class="min-w-0 truncate">{{ auth.user?.name }}</Text>
                        <template #action>
                            <Button variant="outline" size="sm" icon-start="edit" @click="editName">Edit</Button>
                        </template>
                    </SettingRow>
                    <SettingRow label="Email" description="Changed on the server only.">
                        <Text class="min-w-0 truncate">{{ auth.user?.email }}</Text>
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="general" title="General" icon="dashboard" description="Saved on this device.">
                    <SettingRow label="Open Focus on" description="Where you land when you open Focus or sign in.">
                        <ChipGroup v-model="preferences.prefs.startPage" label="Open Focus on" :options="startPages" />
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="appearance" title="Appearance" icon="palette" description="Saved on this device.">
                    <SettingRow
                        label="Theme"
                        :description="
                            preferences.prefs.theme === 'system'
                                ? `Following your device: ${preferences.resolved === 'light' ? 'light' : 'dark'}.`
                                : 'System follows your device’s light or dark setting.'
                        "
                    >
                        <ChipGroup v-model="preferences.prefs.theme" label="Theme" :options="themeOptions" />
                    </SettingRow>
                    <SettingRow
                        v-slot="{ labelId, descriptionId }"
                        label="Reduce motion"
                        :description="
                            preferences.systemReducedMotion
                                ? 'Your device already asks for less motion, so animations are off.'
                                : 'Turns off animations, rolling digits and count-ups.'
                        "
                    >
                        <Switch
                            v-model="preferences.prefs.reduceMotion"
                            :labelledby="labelId"
                            :describedby="descriptionId"
                        />
                    </SettingRow>
                    <SettingRow
                        v-slot="{ labelId, descriptionId }"
                        label="Daily quote"
                        description="On the dashboard and the focus clock."
                    >
                        <Switch
                            v-model="preferences.prefs.showQuotes"
                            :labelledby="labelId"
                            :describedby="descriptionId"
                        />
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="clock" title="Clock & timer" icon="clock" description="Saved on this device.">
                    <SettingRow label="Time format">
                        <ChipGroup v-model="timeFormat" label="Time format" :options="timeFormats" />
                    </SettingRow>
                    <SettingRow v-slot="{ labelId }" label="Show seconds" description="On the focus clock.">
                        <Switch v-model="clock.seconds" :labelledby="labelId" />
                    </SettingRow>
                    <SettingRow
                        v-slot="{ labelId, descriptionId }"
                        label="Ticking sound"
                        description="A soft tick each second while the clock is open."
                    >
                        <Switch v-model="clock.tick" :labelledby="labelId" :describedby="descriptionId" />
                    </SettingRow>
                    <SettingRow
                        v-slot="{ labelId, descriptionId }"
                        label="Timer chime"
                        description="Plays when a focus session or break ends."
                    >
                        <Switch v-model="timer.sound" :labelledby="labelId" :describedby="descriptionId" />
                    </SettingRow>
                    <SettingRow
                        v-slot="{ labelId, descriptionId }"
                        label="Start breaks automatically"
                        description="A 5-minute break begins when a focus session ends."
                    >
                        <Switch v-model="timer.autoBreak" :labelledby="labelId" :describedby="descriptionId" />
                    </SettingRow>
                    <SettingRow
                        label="Desktop notifications"
                        description="Shown when a session ends while Focus is in the background."
                    >
                        <Text :tone="notificationPermission === 'granted' ? undefined : 'muted'">{{
                            notificationStatus
                        }}</Text>
                        <template v-if="notificationsSupported && notificationPermission === 'default'" #action>
                            <Button variant="outline" size="sm" @click="allowNotifications">Allow</Button>
                        </template>
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="security" title="Security" icon="lock">
                    <SettingRow
                        label="Password"
                        description="At least 12 characters with upper and lower case letters and a number."
                    >
                        <Text tone="muted" aria-label="Password set">••••••••••••</Text>
                        <template #action>
                            <Button variant="outline" size="sm" icon-start="key" @click="editPassword">Change</Button>
                        </template>
                    </SettingRow>
                    <SettingRow
                        label="This device"
                        description="Kept in a secure cookie scripts can’t read. Signing out ends it at once."
                    >
                        <div v-if="session" class="flex min-w-0 flex-col gap-1">
                            <Text>{{ session.value }}</Text>
                            <Text role="caption" tone="muted">{{ session.detail }}</Text>
                        </div>
                        <template #action>
                            <Button
                                variant="outline"
                                size="sm"
                                icon-start="logout"
                                :loading="signingOut"
                                @click="signOut"
                                >Sign out</Button
                            >
                        </template>
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="reset" title="Reset" icon="undo">
                    <SettingRow
                        label="Preferences on this device"
                        description="Theme, motion, start page, quotes, clock and timer go back to their defaults. Your tasks and account aren’t touched."
                    >
                        <template #action>
                            <Button variant="outline" size="sm" icon-start="undo" @click="resetOpen = true"
                                >Reset</Button
                            >
                        </template>
                    </SettingRow>
                </SettingsSection>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="Reset preferences?"
            message="Theme, motion, start page, quotes, clock and timer settings on this device go back to their defaults."
            confirm-label="Reset"
            @confirm="resetPreferences"
        />

        <Dialog v-model:open="nameOpen" title="Edit name" size="sm">
            <form class="flex flex-col gap-4" novalidate @submit.prevent="saveName">
                <Input
                    id="profile-name"
                    v-model="name"
                    label="Name"
                    autocomplete="name"
                    maxlength="120"
                    :error="nameError"
                />
                <div class="flex justify-end gap-2">
                    <Button variant="ghost" @click="nameOpen = false">Cancel</Button>
                    <Button type="submit" :loading="savingName">Save</Button>
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:open="passwordOpen"
            title="Change password"
            description="At least 12 characters with upper and lower case letters and a number."
            size="sm"
        >
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
                <div class="flex justify-end gap-2">
                    <Button variant="ghost" @click="passwordOpen = false">Cancel</Button>
                    <Button type="submit" :loading="savingPassword">Change password</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>

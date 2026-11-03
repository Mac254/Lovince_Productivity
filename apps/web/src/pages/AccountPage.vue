<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { IconName, TabItem } from '@focus/ui';
import type { BackupImportResult } from '@focus/types';
import { accentSwatch } from '@focus/tokens';
import { LIMITS, type Settings } from '@focus/types';
import { useAsync } from '@/composables/useAsync';
import { useClockPrefs } from '@/features/clock/wallpapers';
import { formatDate, todayIso } from '@/lib/dates';
import type { StartPage, ThemeChoice } from '@/stores/preferences';

type FontPairing = Settings['appearance']['font'];

type SettingsTab = 'account' | 'preferences' | 'features' | 'data';

const auth = useAuthStore();
const preferences = usePreferencesStore();
const timer = useTimerStore();
const router = useRouter();
const toast = useToast();

const tabs: TabItem<SettingsTab>[] = [
    { value: 'account', label: 'Account', icon: 'user' },
    { value: 'preferences', label: 'Preferences', icon: 'palette' },
    { value: 'features', label: 'Features', icon: 'tasks' },
    { value: 'data', label: 'Data', icon: 'download' },
];
const tab = useTabQuery(['account', 'preferences', 'features', 'data'] as const, 'account');

const themeOptions: { value: ThemeChoice; label: string; icon: IconName }[] = [
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'system', label: 'System', icon: 'monitor' },
];
const accentNames = ['green', 'blue', 'indigo', 'violet', 'rose', 'amber', 'cyan', 'graphite'] as const;
const accentPresets = accentNames.map((name) => ({
    name,
    value: accentSwatch(name),
    label: name[0]!.toUpperCase() + name.slice(1),
}));
const accentColor = computed({
    get: () =>
        accentPresets.find((preset) => preset.name === preferences.prefs.accent)?.value ?? preferences.prefs.accent,
    set: (value: string) => {
        const preset = accentPresets.find((item) => item.value.toLowerCase() === value.toLowerCase());
        preferences.prefs.accent = preset ? preset.name : (value.toLowerCase() as `#${string}`);
    },
});
const cornerOptions = [
    { value: 'sharp' as const, label: 'Sharp' },
    { value: 'soft' as const, label: 'Soft' },
    { value: 'round' as const, label: 'Round' },
];
const descriptions = useAsync(() => api.settings.get());
const fontOptions = computed(() => {
    const setting = descriptions.data.value?.modules
        .find((module) => module.key === 'appearance')
        ?.settings.find((item) => item.key === 'font');
    return (setting?.options ?? []).map((option) => ({ value: option.value as FontPairing, label: option.label }));
});
onMounted(() => void descriptions.run());
const textSizeOptions = [
    { value: 'small' as const, label: 'Small' },
    { value: 'default' as const, label: 'Default' },
    { value: 'large' as const, label: 'Large' },
    { value: 'larger' as const, label: 'Larger' },
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

const clock = useClockPrefs();
const timeFormat = computed({
    get: () => (clock.hour12 ? '12' : '24'),
    set: (value: string) => (clock.hour12 = value === '12'),
});

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

const settings = useSettingsStore();
const dashboard = useDashboardStore();
const goals = useGoalsStore();
const exporting = ref(false);
const importing = ref(false);
const backupFile = ref<File | null>(null);

async function exportData(): Promise<void> {
    exporting.value = true;
    try {
        const url = URL.createObjectURL(await api.backup.export());
        const link = document.createElement('a');
        link.href = url;
        link.download = `focus-backup-${todayIso()}.json`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        toast.error(errorMessage(error, 'Could not export your data.'));
    } finally {
        exporting.value = false;
    }
}

function chooseBackup(file: File): void {
    if (file.size > LIMITS.backupKb * 1024) {
        toast.error(`Backups can be up to ${Math.round(LIMITS.backupKb / 1024)} MB.`);
        return;
    }
    backupFile.value = file;
}

function importSummary(result: BackupImportResult): string {
    const parts: [number, string, string][] = [
        [result.tasks, 'task', 'tasks'],
        [result.goals, 'goal', 'goals'],
        [result.journal, 'journal entry', 'journal entries'],
        [result.blockers, 'blocker', 'blockers'],
        [result.focus_sessions, 'focus session', 'focus sessions'],
        [result.weekly_reviews, 'weekly review', 'weekly reviews'],
        [result.time_blocks, 'time block', 'time blocks'],
        [result.task_types, 'task type', 'task types'],
    ];
    const added = parts
        .filter(([count]) => count > 0)
        .map(([count, one, many]) => `${count} ${count === 1 ? one : many}`);
    return added.length ? `Added ${added.join(', ')}` : 'Nothing new to add';
}

async function importData(): Promise<void> {
    if (!backupFile.value) return;
    importing.value = true;
    try {
        const result = await api.backup.import(backupFile.value);
        backupFile.value = null;
        settings.hydrate((await api.settings.get()).values);
        goals.reset();
        void dashboard.invalidate();
        toast.success(importSummary(result));
    } catch (error) {
        toast.error(errorMessage(error, 'Could not import that file.'));
    } finally {
        importing.value = false;
    }
}

const resetOpen = ref(false);
const resetting = ref(false);
async function resetPreferences(): Promise<void> {
    resetting.value = true;
    try {
        await preferences.reset();
        resetOpen.value = false;
        toast.success('Settings reset to their defaults');
    } catch (error) {
        toast.error(errorMessage(error));
    } finally {
        resetting.value = false;
    }
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
async function upload(file: File): Promise<void> {
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
            description="Your profile, how LovinceTracker looks, and how you sign in."
        />

        <Tabs v-model="tab" label="Settings sections" :tabs="tabs">
            <template #account>
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
                            <FilePicker
                                v-slot="{ open }"
                                accept="image/jpeg,image/png,image/webp"
                                label="Choose a profile photo"
                                @pick="upload"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon-start="camera"
                                    :loading="uploading"
                                    @click="open"
                                    >{{ auth.user?.avatar_url ? 'Replace' : 'Upload' }}</Button
                                >
                            </FilePicker>
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
            </template>
            <template #preferences>
                <SettingsSection
                    id="general"
                    title="General"
                    icon="dashboard"
                    description="Synced across your devices."
                >
                    <SettingRow
                        label="Open LovinceTracker on"
                        description="Where you land when you open LovinceTracker or sign in."
                    >
                        <ChipGroup
                            v-model="preferences.prefs.startPage"
                            label="Open LovinceTracker on"
                            :options="startPages"
                        />
                    </SettingRow>
                </SettingsSection>

                <SettingsSection
                    id="appearance"
                    title="Appearance"
                    icon="palette"
                    description="Synced across your devices."
                >
                    <SettingRow
                        label="Theme"
                        :description="
                            preferences.prefs.theme === 'system'
                                ? `Following your device: ${preferences.resolved === 'light' ? 'light' : 'dark'}.`
                                : 'System follows your device’s light or dark setting.'
                        "
                    >
                        <SegmentedControl v-model="preferences.prefs.theme" label="Theme" :options="themeOptions" />
                    </SettingRow>
                    <SettingRow label="Accent colour" description="Buttons, highlights, charts and the focus ring.">
                        <ColorPicker
                            v-model="accentColor"
                            label="Accent colour"
                            :presets="accentPresets"
                            class="w-full sm:w-80"
                        />
                    </SettingRow>
                    <SettingRow label="Corners" description="How rounded cards, fields and buttons are.">
                        <SegmentedControl
                            v-model="preferences.prefs.corners"
                            label="Corners"
                            :options="cornerOptions"
                        />
                    </SettingRow>
                    <SettingRow label="Typeface" description="Heading and body fonts used across the app.">
                        <FontPicker v-if="fontOptions.length" :options="fontOptions" />
                    </SettingRow>
                    <SettingRow label="Text size" description="Scales text and spacing across the app.">
                        <SegmentedControl
                            v-model="preferences.prefs.textSize"
                            label="Text size"
                            :options="textSizeOptions"
                        />
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

                <SettingsSection
                    id="clock"
                    title="Clock & timer"
                    icon="clock"
                    description="Synced across your devices."
                >
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
                        description="Shown when a session ends while LovinceTracker is in the background."
                    >
                        <Text :tone="notificationPermission === 'granted' ? undefined : 'muted'">{{
                            notificationStatus
                        }}</Text>
                        <template v-if="notificationsSupported && notificationPermission === 'default'" #action>
                            <Button variant="outline" size="sm" @click="allowNotifications">Allow</Button>
                        </template>
                    </SettingRow>
                </SettingsSection>
            </template>
            <template #features>
                <ModuleSettings
                    :modules="['tasks', 'planner', 'timer', 'review', 'journal']"
                    :skip="['timer.chime', 'timer.auto_break', 'journal.font']"
                />
            </template>
            <template #data>
                <SettingsSection
                    id="data"
                    title="Your data"
                    icon="download"
                    description="A single JSON file with everything you’ve written."
                >
                    <SettingRow
                        label="Export"
                        description="Settings, tasks with their work log and checklists, goals, blockers, journal, focus sessions, reviews and time blocks."
                    >
                        <template #action>
                            <Button
                                variant="outline"
                                size="sm"
                                icon-start="download"
                                :loading="exporting"
                                @click="exportData"
                                >Export</Button
                            >
                        </template>
                    </SettingRow>
                    <SettingRow
                        label="Import"
                        description="Adds a backup’s records to this account. Days that already have a journal entry or review keep theirs."
                    >
                        <template #action>
                            <FilePicker
                                v-slot="{ open }"
                                accept="application/json,.json"
                                label="Choose a backup file"
                                @pick="chooseBackup"
                            >
                                <Button variant="outline" size="sm" icon-start="upload" @click="open">Import</Button>
                            </FilePicker>
                        </template>
                    </SettingRow>
                </SettingsSection>

                <SettingsSection id="reset" title="Reset" icon="undo">
                    <SettingRow
                        label="All settings"
                        description="Theme, motion, start page, quotes, journal font, clock and timer go back to their defaults on every device. Your tasks and account aren’t touched."
                    >
                        <template #action>
                            <Button variant="outline" size="sm" icon-start="undo" @click="resetOpen = true"
                                >Reset</Button
                            >
                        </template>
                    </SettingRow>
                </SettingsSection>
            </template>
        </Tabs>

        <ConfirmDialog
            :open="backupFile !== null"
            title="Import this backup?"
            :message="`Everything in ${backupFile?.name ?? 'the file'} is added to your account. Nothing you have now is deleted.`"
            confirm-label="Import"
            :loading="importing"
            @update:open="(value: boolean) => !value && (backupFile = null)"
            @confirm="importData"
        />
        <ConfirmDialog
            v-model:open="resetOpen"
            title="Reset settings?"
            message="Theme, motion, start page, quotes, journal font, clock and timer settings go back to their defaults on every device you use."
            confirm-label="Reset"
            :loading="resetting"
            @confirm="resetPreferences"
        />

        <Dialog v-model:open="nameOpen" title="Edit name" size="sm" :busy="savingName">
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
                    <Button variant="ghost" :disabled="savingName" @click="nameOpen = false">Cancel</Button>
                    <Button type="submit" :loading="savingName">Save</Button>
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:open="passwordOpen"
            title="Change password"
            description="At least 12 characters with upper and lower case letters and a number."
            size="sm"
            :busy="savingPassword"
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
                    <Button variant="ghost" :disabled="savingPassword" @click="passwordOpen = false">Cancel</Button>
                    <Button type="submit" :loading="savingPassword">Change password</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>

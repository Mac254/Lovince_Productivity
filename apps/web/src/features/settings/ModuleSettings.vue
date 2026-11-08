<script setup lang="ts">
import { api } from '@focus/api-client';
import type { IconName } from '@focus/ui';
import type { SettingDescription, Settings, SettingsModuleKey } from '@focus/types';
import { useAsync } from '@/composables/useAsync';

const props = defineProps<{ modules: SettingsModuleKey[]; skip?: string[] }>();

const icons: Partial<Record<SettingsModuleKey, IconName>> = {
    tasks: 'tasks',
    planner: 'calendar-clock',
    timer: 'hourglass',
    review: 'calendar-check',
    journal: 'journal',
    books: 'book',
    roadmap: 'roadmap',
};

const settings = useSettingsStore();
const state = useAsync(() => api.settings.get());

const sections = computed(() =>
    (state.data.value?.modules ?? [])
        .filter((module) => props.modules.includes(module.key as SettingsModuleKey))
        .map((module) => ({
            ...module,
            key: module.key as SettingsModuleKey,
            settings: module.settings.filter(
                (setting) => setting.listed && !props.skip?.includes(`${module.key}.${setting.key}`),
            ),
        }))
        .filter((module) => module.settings.length),
);

function value(module: SettingsModuleKey, key: string): boolean | string | undefined {
    return (settings.values[module] as Record<string, boolean | string>)[key];
}

function update(module: SettingsModuleKey, setting: SettingDescription, next: boolean | string): void {
    settings.set(module, setting.key as keyof Settings[typeof module], next as never);
}

onMounted(() => void state.run());
</script>

<template>
    <ErrorState v-if="state.error.value" :message="state.error.value" @retry="state.run()" />
    <template v-else-if="!state.data.value">
        <Skeleton v-for="n in 2" :key="n" class="h-40 rounded-lg" />
    </template>
    <SettingsSection
        v-for="module in sections"
        v-else
        :id="`module-${module.key}`"
        :key="module.key"
        :title="module.label"
        :icon="icons[module.key] ?? 'dashboard'"
        :description="module.description"
    >
        <SettingRow
            v-for="setting in module.settings"
            :key="setting.key"
            v-slot="{ labelId, descriptionId }"
            :label="setting.label"
            :description="setting.description"
        >
            <Switch
                v-if="setting.type === 'boolean'"
                :model-value="value(module.key, setting.key) === true"
                :labelledby="labelId"
                :describedby="descriptionId"
                @update:model-value="(next: boolean) => update(module.key, setting, next)"
            />
            <ChipGroup
                v-else
                :model-value="String(value(module.key, setting.key))"
                :label="setting.label"
                :options="setting.options ?? []"
                @update:model-value="(next: string) => update(module.key, setting, next)"
            />
        </SettingRow>
    </SettingsSection>
</template>

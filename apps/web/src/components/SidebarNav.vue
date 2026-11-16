<script setup lang="ts">
import { visibleNavigation } from '@/lib/navigation';

defineProps<{ collapsed?: boolean }>();
const emit = defineEmits<{ navigate: [] }>();
const route = useRoute();
const settings = useSettingsStore();
const sections = computed(() => visibleNavigation(settings.values));
const nav = ref<HTMLElement | null>(null);
useLiquid(nav);

const GROUPS_KEY = 'focus.sidebar-folded';
const folded = ref<string[]>(readFolded());
function readFolded(): string[] {
    try {
        const saved: unknown = JSON.parse(localStorage.getItem(GROUPS_KEY) ?? '[]');
        return Array.isArray(saved) ? saved.filter((label): label is string => typeof label === 'string') : [];
    } catch {
        return [];
    }
}
watch(folded, (labels) => {
    try {
        localStorage.setItem(GROUPS_KEY, JSON.stringify(labels));
    } catch {
        /* Groups still fold; they just won't be remembered. */
    }
});
function toggle(label: string): void {
    folded.value = folded.value.includes(label)
        ? folded.value.filter((other) => other !== label)
        : [...folded.value, label];
}
const groupId = (label: string) => `nav-group-${label.toLowerCase().replace(/\W+/g, '-')}`;

/** Detail pages (e.g. a task) keep their section highlighted. */
function isActive(name: string): boolean {
    const current = String(route.name ?? '');
    return current === name || current.startsWith(`${name.replace(/s$/, '')}-`) || current.startsWith(`${name}-`);
}
</script>

<template>
    <nav ref="nav" data-liquid aria-label="Primary" class="flex flex-col gap-6">
        <span data-liquid-indicator aria-hidden="true" class="bg-highlight" />
        <div v-for="section in sections" :key="section.label" class="flex flex-col gap-1">
            <p v-if="collapsed" class="sr-only">{{ section.label }}</p>
            <button
                v-else
                type="button"
                :aria-expanded="!folded.includes(section.label)"
                :aria-controls="groupId(section.label)"
                class="group flex items-center justify-between rounded-md px-3 pb-1 text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase transition-colors duration-(--duration-fast) hover:text-chrome-fg"
                @click="toggle(section.label)"
            >
                {{ section.label }}
                <Icon :name="folded.includes(section.label) ? 'chevron-right' : 'chevron-down'" size="sm" />
            </button>
            <div :id="groupId(section.label)" class="contents">
                <template v-for="item in section.items" :key="item.name">
                    <Tooltip
                        v-if="collapsed || !folded.includes(section.label) || isActive(item.name)"
                        :label="item.label"
                        side="right"
                        :disabled="!collapsed"
                    >
                        <RouterLink
                            :to="{ name: item.name }"
                            :aria-current="isActive(item.name) ? 'page' : undefined"
                            data-liquid-item
                            :class="[
                                'group flex items-center gap-3 rounded-md px-3 py-2 text-label font-semibold transition-colors duration-(--duration-fast)',
                                collapsed && 'justify-center',
                                isActive(item.name) ? 'text-highlight-ink' : 'text-chrome-fg hover:bg-chrome-hover',
                            ]"
                            @click="emit('navigate')"
                        >
                            <Icon :name="item.icon" size="md" :motion="item.motion" />
                            <span :class="collapsed && 'sr-only'">{{ item.label }}</span>
                        </RouterLink>
                    </Tooltip>
                </template>
            </div>
        </div>
    </nav>
</template>

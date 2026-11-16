<script setup lang="ts">
import { visibleNavigation } from '@/lib/navigation';

defineProps<{ collapsed?: boolean }>();
const emit = defineEmits<{ navigate: [] }>();
const route = useRoute();
const settings = useSettingsStore();
const sections = computed(() => visibleNavigation(settings.values));
const nav = ref<HTMLElement | null>(null);
useLiquid(nav);

/** Only the group in use is open: the one holding the current page, or the one just opened. */
const open = ref<string | null>(null);
const current = computed(
    () => sections.value.find((section) => section.items.some((item) => isActive(item.name)))?.label ?? null,
);
watch(
    () => route.name,
    () => (open.value = current.value),
    { immediate: true },
);
function toggle(label: string): void {
    open.value = open.value === label ? null : label;
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
                :aria-expanded="open === section.label"
                :aria-controls="groupId(section.label)"
                class="group flex items-center justify-between rounded-md px-3 pb-1 text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase transition-colors duration-(--duration-fast) hover:text-chrome-fg"
                @click="toggle(section.label)"
            >
                {{ section.label }}
                <Icon :name="open !== section.label ? 'chevron-right' : 'chevron-down'" size="sm" />
            </button>
            <div :id="groupId(section.label)" class="contents">
                <template v-for="item in section.items" :key="item.name">
                    <Tooltip
                        v-if="collapsed || open === section.label || isActive(item.name)"
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

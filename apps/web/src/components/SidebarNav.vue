<script setup lang="ts">
import { navigation } from '@/lib/navigation';

const emit = defineEmits<{ navigate: [] }>();
const route = useRoute();
const nav = ref<HTMLElement | null>(null);
useLiquid(nav);

/** Detail pages (e.g. a task) keep their section highlighted. */
function isActive(name: string): boolean {
    const current = String(route.name ?? '');
    return current === name || current.startsWith(`${name.replace(/s$/, '')}-`) || current.startsWith(`${name}-`);
}
</script>

<template>
    <nav ref="nav" data-liquid aria-label="Primary" class="flex flex-col gap-6">
        <span data-liquid-indicator aria-hidden="true" class="bg-highlight" />
        <div v-for="section in navigation" :key="section.label" class="flex flex-col gap-1">
            <p class="px-3 pb-1 text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase">
                {{ section.label }}
            </p>
            <RouterLink
                v-for="item in section.items"
                :key="item.name"
                :to="{ name: item.name }"
                :aria-current="isActive(item.name) ? 'page' : undefined"
                data-liquid-item
                :class="[
                    'group flex items-center gap-3 rounded-md px-3 py-2 text-label font-semibold transition-colors duration-(--duration-fast)',
                    isActive(item.name) ? 'text-highlight-ink' : 'text-chrome-fg hover:bg-chrome-hover',
                ]"
                @click="emit('navigate')"
            >
                <Icon :name="item.icon" size="md" :motion="item.motion" />
                {{ item.label }}
            </RouterLink>
        </div>
    </nav>
</template>

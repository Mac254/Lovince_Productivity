<script setup lang="ts">
import { emojiGroups } from './emoji';

const emit = defineEmits<{ pick: [char: string]; close: [] }>();

const COLUMNS = 8;
const query = ref('');
const group = ref(emojiGroups[0]!.value);
const active = ref(0);
const grid = ref<HTMLElement | null>(null);

const groupOptions = emojiGroups.map(({ value, label }) => ({ value, label }));
const shown = computed(() => {
    const term = query.value.trim().toLowerCase();
    if (!term) return emojiGroups.find((item) => item.value === group.value)?.emoji ?? [];
    return emojiGroups.flatMap((item) => item.emoji).filter((emoji) => emoji.name.includes(term));
});

watch(shown, () => (active.value = 0));
onMounted(() => document.getElementById('journal-emoji-search')?.focus());

function focusCell(index: number): void {
    active.value = Math.max(0, Math.min(shown.value.length - 1, index));
    nextTick(() => grid.value?.querySelectorAll<HTMLButtonElement>('button')[active.value]?.focus());
}

function onGridKeydown(event: KeyboardEvent): void {
    const moves: Record<string, number> = {
        ArrowLeft: active.value - 1,
        ArrowRight: active.value + 1,
        ArrowUp: active.value - COLUMNS,
        ArrowDown: active.value + COLUMNS,
        Home: 0,
        End: shown.value.length - 1,
    };
    const target = moves[event.key];
    if (target === undefined) return;
    event.preventDefault();
    focusCell(target);
}
</script>

<template>
    <div
        class="flex flex-col gap-3 border-b border-border bg-surface-raised px-4 py-4 sm:px-6"
        @keydown.esc.stop.prevent="emit('close')"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="sm:w-56 sm:shrink-0">
                <SearchField
                    id="journal-emoji-search"
                    v-model="query"
                    label="Search emoji"
                    placeholder="Search emoji"
                />
            </div>
            <ChipGroup v-if="!query.trim()" v-model="group" label="Emoji group" :options="groupOptions" />
        </div>
        <div
            v-if="shown.length"
            ref="grid"
            role="group"
            aria-label="Emoji"
            class="grid grid-cols-8 gap-1 sm:w-fit"
            @keydown="onGridKeydown"
        >
            <button
                v-for="(emoji, index) in shown"
                :key="emoji.char"
                type="button"
                :tabindex="index === active ? 0 : -1"
                :aria-label="emoji.name"
                :title="emoji.name"
                class="emoji inline-flex size-(--control-md) items-center justify-center rounded-md transition-colors duration-(--duration-instant) hover:bg-surface focus-visible:bg-surface focus-visible:outline-2 focus-visible:outline-focus-ring"
                @focus="active = index"
                @click="emit('pick', emoji.char)"
            >
                {{ emoji.char }}
            </button>
        </div>
        <p v-else role="status" class="text-label text-fg-muted">No emoji match “{{ query }}”.</p>
    </div>
</template>

<style scoped>
.emoji {
    font-size: 1.375rem;
    line-height: 1;
}
</style>

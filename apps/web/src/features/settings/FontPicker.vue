<script setup lang="ts">
import { fontGroups, fonts, type FontGroup, type FontKey } from './fonts';

const props = defineProps<{ options: { value: FontKey; label: string }[] }>();

const preferences = usePreferencesStore();
const open = ref(false);
const query = ref('');
const group = ref<FontGroup | 'all'>('all');
const selected = ref<FontKey>(preferences.prefs.font);

const groupOptions = [{ value: 'all' as const, label: 'All' }, ...fontGroups];
const groupLabel = (value: FontGroup) => fontGroups.find((item) => item.value === value)?.label ?? '';

const current = computed(() => props.options.find((option) => option.value === preferences.prefs.font));
const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();
    return props.options.filter((option) => {
        const fontGroup = fonts[option.value]?.group ?? 'sans';
        if (group.value !== 'all' && fontGroup !== group.value) return false;
        return !term || `${option.label} ${groupLabel(fontGroup)}`.toLowerCase().includes(term);
    });
});

function show(): void {
    selected.value = preferences.prefs.font;
    query.value = '';
    group.value = 'all';
    open.value = true;
}

function apply(): void {
    preferences.prefs.font = selected.value;
    open.value = false;
}

watch([open, selected], ([isOpen, font]) => {
    preferences.previewFont = isOpen ? font : null;
});
onBeforeUnmount(() => (preferences.previewFont = null));
</script>

<template>
    <button
        type="button"
        :data-font="preferences.prefs.font"
        aria-haspopup="dialog"
        class="flex w-full items-center justify-between gap-4 rounded-lg border border-border-control px-4 py-3 text-left transition-colors duration-(--duration-instant) hover:border-border-strong hover:bg-surface focus-visible:outline-2 focus-visible:outline-focus-ring sm:w-80"
        @click="show"
    >
        <span class="flex min-w-0 flex-col">
            <span class="truncate font-display text-title font-semibold tracking-tight">{{
                current?.label ?? 'Typeface'
            }}</span>
            <span class="truncate font-sans text-caption text-fg-muted">Plan the day, finish the work.</span>
        </span>
        <span class="shrink-0 font-sans text-label font-semibold text-fg-muted">Change</span>
    </button>

    <Dialog
        v-model:open="open"
        title="Typeface"
        description="Choose a heading and body pairing. Your pick previews across the app until you apply or cancel."
        size="lg"
        initial-focus="font-search"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3">
                <SearchField id="font-search" v-model="query" label="Search typefaces" placeholder="Search typefaces" />
                <ChipGroup v-model="group" label="Font style" :options="groupOptions" />
            </div>
            <RadioGroup
                v-if="filtered.length"
                v-model="selected"
                :label="`${filtered.length} ${filtered.length === 1 ? 'typeface' : 'typefaces'}`"
                :options="filtered"
                layout="cards"
            >
                <template #option="{ option }">
                    <FontSample :font="option.value" />
                </template>
            </RadioGroup>
            <p v-else role="status" class="py-8 text-center text-label text-fg-muted">
                No typefaces match “{{ query }}”.
            </p>
        </div>
        <template #footer>
            <Button variant="ghost" @click="open = false">Cancel</Button>
            <Button :disabled="selected === preferences.prefs.font" @click="apply">Apply</Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { api } from '@focus/api-client';
import { useAsync } from '@/composables/useAsync';
import {
    journalFontGroups,
    journalFonts,
    loadJournalFont,
    type JournalFont,
    type JournalFontGroup,
} from './journalFonts';

const open = defineModel<boolean>('open', { required: true });
const font = defineModel<JournalFont>('font', { required: true });
const preview = defineModel<JournalFont | null>('preview', { default: null });

const descriptions = useAsync(() => api.settings.get());
const options = computed(() => {
    const setting = descriptions.data.value?.modules
        .find((module) => module.key === 'journal')
        ?.settings.find((item) => item.key === 'font');
    return (setting?.options ?? []).map((option) => ({ value: option.value as JournalFont, label: option.label }));
});

const group = ref<JournalFontGroup | 'all'>('all');
const selected = ref<JournalFont>(font.value);
const groupOptions = [{ value: 'all' as const, label: 'All' }, ...journalFontGroups];
const filtered = computed(() =>
    options.value.filter((option) => group.value === 'all' || journalFonts[option.value]?.group === group.value),
);

watch(open, (isOpen) => {
    if (!isOpen) return void (preview.value = null);
    selected.value = font.value;
    group.value = 'all';
    if (!descriptions.data.value) void descriptions.run();
});
watch(selected, (value) => {
    if (open.value) preview.value = value;
});
watch(filtered, (list) => list.forEach((option) => void loadJournalFont(option.value)));

function apply(): void {
    font.value = selected.value;
    open.value = false;
}
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Writing font"
        description="Your pick shows in the editor straight away. Apply keeps it on every device."
        size="lg"
    >
        <div class="flex flex-col gap-4">
            <ChipGroup v-model="group" label="Font style" :options="groupOptions" />
            <RadioGroup
                v-if="filtered.length"
                v-model="selected"
                :label="`${filtered.length} writing fonts`"
                :options="filtered"
                layout="cards"
            >
                <template #option="{ option }">
                    <span
                        :data-journal-font="option.value"
                        :class="['sample w-full font-medium text-fg', journalFonts[option.value]?.size ?? 'text-body']"
                    >
                        Today I shipped the thing I’d been circling all week.
                    </span>
                </template>
            </RadioGroup>
            <ErrorState
                v-else-if="descriptions.error.value"
                :message="descriptions.error.value"
                @retry="descriptions.run()"
            />
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Skeleton v-for="n in 6" :key="n" class="h-24 rounded-lg" />
            </div>
        </div>
        <template #footer>
            <Button variant="ghost" @click="open = false">Cancel</Button>
            <Button :disabled="selected === font" @click="apply">Apply</Button>
        </template>
    </Dialog>
</template>

<style scoped>
.sample {
    font-family: var(--journal-font);
    line-height: 1.4;
}
</style>

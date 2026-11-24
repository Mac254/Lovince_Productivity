<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Book, type BookReading } from '@focus/types';
import { parseDuration, todayIso } from '@/lib/dates';
import { toCount } from './progress';

const props = defineProps<{ book: Book | null }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [reading: BookReading] }>();

const toast = useToast();
const form = reactive({
    read_on: '' as string | null,
    episode: '' as string | null,
    chapters: '' as string | null,
    minutes: '' as string | null,
    note: '' as string | null,
    impact: '' as string | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const podcast = computed(() => props.book?.kind === 'podcast');
const nextChapter = computed(() => (props.book ? props.book.chapters_read + 1 : 1));
const description = computed(() => {
    const book = props.book;
    if (!book) return '';
    if (podcast.value)
        return 'Note what the episode said and how it’s affecting you, so you can see its effect over time.';
    const total = book.chapters_total ? ` of ${book.chapters_total}` : '';
    return `You’re on chapter ${nextChapter.value}${total}. Log what you read in this sitting.`;
});

watch(open, (value) => {
    if (!value) return;
    Object.assign(form, {
        read_on: todayIso(),
        episode: '',
        chapters: podcast.value ? '0' : '1',
        minutes: '',
        note: '',
        impact: '',
    });
    errors.value = {};
});

async function submit(): Promise<void> {
    if (!props.book) return;
    errors.value = {};
    const chapters = podcast.value ? 0 : (toCount(form.chapters) ?? 0);
    const minutes = form.minutes?.trim() ? parseDuration(form.minutes) : null;
    if (!form.read_on) errors.value.read_on = podcast.value ? 'Pick the day you listened.' : 'Pick the day you read.';
    if (Number.isNaN(chapters) || chapters > LIMITS.book.readingChapters)
        errors.value.chapters = `Use a whole number up to ${LIMITS.book.readingChapters}.`;
    if (form.minutes?.trim() && (minutes === null || minutes > LIMITS.book.readingMinutes))
        errors.value.minutes = 'Use minutes or hours, like 30m or 1h 15m, up to 12 hours.';
    const logged = chapters > 0 || minutes !== null || !!form.note?.trim() || !!form.episode?.trim();
    if (!Object.keys(errors.value).length && !logged) {
        if (podcast.value) errors.value.episode = 'Name the episode, or add the time or a note.';
        else errors.value.chapters = 'Log at least a chapter, some minutes or a note.';
    }
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    try {
        const saved = await api.books.logReading(props.book.id, {
            read_on: form.read_on!,
            episode: podcast.value ? form.episode?.trim() || null : null,
            chapters,
            minutes,
            note: form.note?.trim() || null,
            impact: podcast.value ? form.impact?.trim() || null : null,
        });
        toast.success(
            podcast.value
                ? 'Episode logged'
                : chapters
                  ? `Logged ${chapters} ${chapters === 1 ? 'chapter' : 'chapters'}`
                  : 'Reading logged',
        );
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, podcast.value ? 'Could not log the episode.' : 'Could not log the reading.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="book ? `${podcast ? 'Log episode' : 'Log reading'} · ${book.title}` : 'Log reading'"
        :description="description"
        :busy="saving"
    >
        <form id="reading-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <div v-if="podcast" class="grid gap-6 sm:grid-cols-3">
                <DatePicker id="reading-date" v-model="form.read_on" label="Day" :error="errors.read_on" />
                <Input
                    id="reading-episode"
                    v-model="form.episode"
                    label="Episode"
                    :maxlength="LIMITS.book.title"
                    :error="errors.episode"
                    autofocus
                />
                <Input
                    id="reading-minutes"
                    v-model="form.minutes"
                    label="Time"
                    placeholder="e.g. 45m"
                    optional
                    :error="errors.minutes"
                />
            </div>
            <div v-else class="grid gap-6 sm:grid-cols-3">
                <DatePicker id="reading-date" v-model="form.read_on" label="Day" :error="errors.read_on" />
                <Input
                    id="reading-chapters"
                    v-model="form.chapters"
                    label="Chapters read"
                    inputmode="numeric"
                    :error="errors.chapters"
                    autofocus
                />
                <Input
                    id="reading-minutes"
                    v-model="form.minutes"
                    label="Time"
                    placeholder="e.g. 45m"
                    optional
                    :error="errors.minutes"
                />
            </div>
            <Textarea
                id="reading-note"
                v-model="form.note"
                :label="podcast ? 'What it said' : 'What stayed with you'"
                optional
                :maxlength="LIMITS.book.readingNote"
                :error="errors.note"
            />
            <Textarea
                v-if="podcast"
                id="reading-impact"
                v-model="form.impact"
                label="How it’s affecting me"
                optional
                hint="What you’ll do, think or feel differently because of it"
                :maxlength="LIMITS.book.readingNote"
                :error="errors.impact"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="reading-form" :loading="saving" :icon-start="podcast ? 'headphones' : 'book'">{{
                podcast ? 'Log episode' : 'Log reading'
            }}</Button>
        </template>
    </Dialog>
</template>

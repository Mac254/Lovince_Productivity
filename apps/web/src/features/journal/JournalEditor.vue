<script setup lang="ts">
import { LIMITS } from '@focus/types';
import { computed, ref } from 'vue';
import { caretPosition, continueList, indentList, type Edit } from '@/lib/textEditing';

/**
 * The journal's writing surface: a plain textarea with editor manners. Enter continues lists,
 * Tab nests list items (and otherwise moves focus as usual), and a status bar shows the caret,
 * words and save state. Edits go through the browser's own insert so Ctrl+Z still undoes them.
 */
const props = defineProps<{ label: string; disabled?: boolean; saveState: string }>();
const emit = defineEmits<{ save: []; previous: []; next: [] }>();
const body = defineModel<string>({ required: true });
const font = defineModel<'mono' | 'sans'>('font', { required: true });

const textarea = ref<HTMLTextAreaElement | null>(null);
const caret = ref({ line: 1, column: 1 });

const words = computed(() => body.value.trim().split(/\s+/).filter(Boolean).length);
const readMinutes = computed(() => Math.max(1, Math.round(words.value / 200)));
const nearLimit = computed(() => body.value.length > LIMITS.journal * 0.9);

function trackCaret(): void {
    const el = textarea.value;
    if (el) caret.value = caretPosition(el.value, el.selectionStart);
}

function apply(edit: Edit): void {
    const el = textarea.value;
    if (!el) return;
    el.focus();
    el.setSelectionRange(edit.from, edit.to);
    const done =
        edit.from === edit.to && !edit.text
            ? true
            : document.execCommand(edit.text ? 'insertText' : 'delete', false, edit.text);
    if (!done) {
        el.setRangeText(edit.text, edit.from, edit.to, 'end');
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
    el.setSelectionRange(edit.caret, edit.caret);
    trackCaret();
}

/** Adds text at the end of the entry (or as the entry, when it is empty) and puts the caret after it. */
function append(text: string): void {
    const current = body.value;
    const end = current.length;
    const gap = !current.trim() ? '' : current.endsWith('\n\n') ? '' : current.endsWith('\n') ? '\n' : '\n\n';
    const from = current.trim() ? end : 0;
    apply({ from, to: end, text: gap + text, caret: from + gap.length + text.length });
    textarea.value?.scrollTo({ top: textarea.value.scrollHeight });
}

function onKeydown(event: KeyboardEvent): void {
    const el = event.target as HTMLTextAreaElement;
    const mod = event.ctrlKey || event.metaKey;

    if (mod && event.key.toLowerCase() === 's') {
        event.preventDefault();
        emit('save');
    } else if (event.altKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        event.preventDefault();
        if (event.key === 'ArrowLeft') emit('previous');
        else emit('next');
    } else if (event.key === 'Enter' && !mod && !event.shiftKey && !event.altKey && !event.isComposing) {
        const edit = el.selectionStart === el.selectionEnd ? continueList(el.value, el.selectionStart) : null;
        if (edit) {
            event.preventDefault();
            apply(edit);
        }
    } else if (event.key === 'Tab' && !mod && !event.altKey) {
        const edit = indentList(el.value, el.selectionStart, event.shiftKey);
        if (edit) {
            event.preventDefault();
            apply(edit);
        }
    }
}

defineExpose({ append, focus: () => textarea.value?.focus() });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <label for="journal-body" class="sr-only">{{ props.label }}</label>
        <textarea
            id="journal-body"
            ref="textarea"
            v-model="body"
            :maxlength="LIMITS.journal"
            :disabled="disabled"
            rows="22"
            spellcheck="true"
            placeholder="## Done&#10;- &#10;&#10;## Learned&#10;- &#10;&#10;## Next&#10;- "
            :class="[
                'editor min-h-96 w-full flex-1 resize-y bg-transparent px-6 py-6 text-fg placeholder:text-fg-subtle focus-visible:outline-none',
                font === 'mono' ? 'font-mono text-label' : 'text-body',
            ]"
            @keydown="onKeydown"
            @keyup="trackCaret"
            @click="trackCaret"
            @select="trackCaret"
            @input="trackCaret"
        />
        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-border px-6 py-2 font-mono text-caption text-fg-muted"
        >
            <span class="flex flex-wrap gap-x-4 gap-y-1 tabular">
                <span>Ln {{ caret.line }}, Col {{ caret.column }}</span>
                <span>{{ words.toLocaleString() }} {{ words === 1 ? 'word' : 'words' }}</span>
                <span v-if="words">{{ readMinutes }} min read</span>
                <span v-if="nearLimit" class="text-fg"
                    >{{ body.length.toLocaleString() }} / {{ LIMITS.journal.toLocaleString() }}</span
                >
            </span>
            <span class="flex items-center gap-4">
                <button
                    type="button"
                    class="rounded-sm underline-offset-4 hover:text-fg hover:underline"
                    :aria-label="`Writing font: ${font === 'mono' ? 'monospace' : 'sans serif'}. Switch font`"
                    @click="font = font === 'mono' ? 'sans' : 'mono'"
                >
                    {{ font === 'mono' ? 'Mono' : 'Sans' }}
                </button>
                <span class="max-sm:hidden">Markdown</span>
                <span aria-live="polite">{{ saveState }}</span>
            </span>
        </div>
    </div>
</template>

<style scoped>
.editor {
    line-height: 1.75;
    tab-size: 2;
}
</style>

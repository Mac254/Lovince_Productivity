<script setup lang="ts">
import { LIMITS } from '@focus/types';
import type { IconName } from '@focus/ui';
import {
    caretPosition,
    continueList,
    indentList,
    insertAt,
    toggleLinePrefix,
    wrapSelection,
    type Edit,
} from '@/lib/textEditing';
import { journalFonts, loadJournalFont, type JournalFont } from './journalFonts';

/**
 * The journal's writing surface: a plain textarea with editor manners. Enter continues lists,
 * Tab nests list items (and otherwise moves focus as usual), and a status bar shows the caret,
 * words and save state. Edits go through the browser's own insert so Ctrl+Z still undoes them.
 */
const props = defineProps<{ label: string; disabled?: boolean; saveState: string }>();
const emit = defineEmits<{ save: []; previous: []; next: [] }>();
const body = defineModel<string>({ required: true });
const font = defineModel<JournalFont>('font', { required: true });

const fontOpen = ref(false);
const previewFont = ref<JournalFont | null>(null);
const activeFont = computed(() => previewFont.value ?? font.value);
watch(activeFont, (value) => void loadJournalFont(value), { immediate: true });

const emojiOpen = ref(false);
const emojiButton = ref<{ $el: HTMLElement } | null>(null);

interface Tool {
    icon: IconName;
    label: string;
    shortcut?: string;
    run: (el: HTMLTextAreaElement) => Edit;
}

const wrap = (marker: string) => (el: HTMLTextAreaElement) =>
    wrapSelection(el.value, el.selectionStart, el.selectionEnd, marker);
const prefix = (text: string) => (el: HTMLTextAreaElement) =>
    toggleLinePrefix(el.value, el.selectionStart, el.selectionEnd, text);

const toolGroups: Tool[][] = [
    [
        { icon: 'bold', label: 'Bold', shortcut: 'Ctrl+B', run: wrap('**') },
        { icon: 'italic', label: 'Italic', shortcut: 'Ctrl+I', run: wrap('*') },
        { icon: 'highlight', label: 'Highlight', shortcut: 'Ctrl+Shift+H', run: wrap('==') },
        { icon: 'strikethrough', label: 'Strikethrough', shortcut: 'Ctrl+Shift+X', run: wrap('~~') },
        { icon: 'code', label: 'Inline code', shortcut: 'Ctrl+E', run: wrap('`') },
    ],
    [
        { icon: 'heading', label: 'Heading', run: prefix('## ') },
        { icon: 'list-bullet', label: 'Bulleted list', run: prefix('- ') },
        { icon: 'list-ordered', label: 'Numbered list', run: prefix('1. ') },
        { icon: 'checklist', label: 'Checklist', run: prefix('- [ ] ') },
        { icon: 'quote', label: 'Quote', run: prefix('> ') },
    ],
];
const shortcuts: Record<string, Tool> = {
    b: toolGroups[0]![0]!,
    i: toolGroups[0]![1]!,
    'shift+h': toolGroups[0]![2]!,
    'shift+x': toolGroups[0]![3]!,
    e: toolGroups[0]![4]!,
};
const toolbar = ref<HTMLElement | null>(null);
const activeTool = ref(0);
const toolIndex = (group: number, index: number) =>
    toolGroups.slice(0, group).reduce((sum, tools) => sum + tools.length, 0) + index;

function runTool(tool: Tool): void {
    const el = textarea.value;
    if (el && !props.disabled) apply(tool.run(el));
}

function onToolbarKeydown(event: KeyboardEvent): void {
    const buttons = [...(toolbar.value?.querySelectorAll<HTMLElement>('[data-tool]') ?? [])];
    const current = buttons.indexOf(document.activeElement as HTMLElement);
    if (current === -1) return;
    const target = { ArrowLeft: current - 1, ArrowRight: current + 1, Home: 0, End: buttons.length - 1 }[event.key];
    if (target === undefined) return;
    event.preventDefault();
    const next = (target + buttons.length) % buttons.length;
    activeTool.value = next;
    buttons[next]?.focus();
}

function insertEmoji(char: string): void {
    const el = textarea.value;
    if (!el) return;
    apply(insertAt(el.selectionStart, el.selectionEnd, char));
    emojiOpen.value = false;
}

function closeEmoji(): void {
    emojiOpen.value = false;
    emojiButton.value?.$el.focus();
}

const editor = ref<{ $el: HTMLTextAreaElement } | null>(null);
const textarea = computed(() => editor.value?.$el ?? null);
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
    el.setSelectionRange(
        Math.min(edit.anchor ?? edit.caret, edit.caret),
        Math.max(edit.anchor ?? edit.caret, edit.caret),
    );
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

    const tool = mod && !event.altKey ? shortcuts[`${event.shiftKey ? 'shift+' : ''}${event.key.toLowerCase()}`] : null;

    if (mod && event.key.toLowerCase() === 's') {
        event.preventDefault();
        emit('save');
    } else if (tool) {
        event.preventDefault();
        runTool(tool);
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
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2 sm:px-4">
            <div
                ref="toolbar"
                role="toolbar"
                aria-label="Formatting"
                aria-controls="journal-body"
                class="flex flex-wrap items-center gap-1"
                @keydown="onToolbarKeydown"
            >
                <template v-for="(tools, group) in toolGroups" :key="group">
                    <span v-if="group" aria-hidden="true" class="mx-1 h-5 w-px bg-border" />
                    <IconButton
                        v-for="(tool, index) in tools"
                        :key="tool.label"
                        data-tool
                        :icon="tool.icon"
                        :label="tool.shortcut ? `${tool.label} (${tool.shortcut})` : tool.label"
                        variant="ghost"
                        size="sm"
                        :disabled="disabled"
                        :tabindex="toolIndex(group, index) === activeTool ? 0 : -1"
                        @click="runTool(tool)"
                    />
                </template>
                <span aria-hidden="true" class="mx-1 h-5 w-px bg-border" />
                <IconButton
                    ref="emojiButton"
                    data-tool
                    icon="emoji"
                    label="Emoji"
                    variant="ghost"
                    size="sm"
                    :disabled="disabled"
                    :aria-expanded="emojiOpen"
                    aria-controls="journal-emoji"
                    :tabindex="toolIndex(toolGroups.length, 0) === activeTool ? 0 : -1"
                    @click="emojiOpen = !emojiOpen"
                />
            </div>
            <Button variant="ghost" size="sm" icon-start="text-format" @click="fontOpen = true">
                <span :data-journal-font="font" class="font-sample">{{ journalFonts[font].name }}</span>
                <span class="sr-only">, change writing font</span>
            </Button>
        </div>

        <EmojiPanel v-if="emojiOpen" id="journal-emoji" @pick="insertEmoji" @close="closeEmoji" />

        <label for="journal-body" class="sr-only">{{ props.label }}</label>
        <PlainTextarea
            id="journal-body"
            ref="editor"
            v-model="body"
            :maxlength="LIMITS.journal"
            :disabled="disabled"
            :rows="22"
            :data-journal-font="activeFont"
            style="field-sizing: fixed"
            spellcheck="true"
            placeholder="## Done&#10;- &#10;&#10;## Learned&#10;- &#10;&#10;## Next&#10;- "
            :class="[
                'editor mx-auto min-h-96 w-full max-w-prose flex-1 resize-y bg-transparent px-6 py-8 text-fg placeholder:text-fg-subtle focus-visible:outline-none',
                journalFonts[activeFont].size,
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
                <span class="max-sm:hidden">Markdown · ==highlight== · *italic*</span>
                <span aria-live="polite">{{ saveState }}</span>
            </span>
        </div>

        <JournalFontPicker v-model:open="fontOpen" v-model:font="font" v-model:preview="previewFont" />
    </div>
</template>

<style scoped>
.editor {
    font-family: var(--journal-font);
    line-height: 1.75;
    tab-size: 2;
}

.font-sample {
    font-family: var(--journal-font);
}
</style>

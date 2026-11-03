import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import JournalEditor from '@/features/journal/JournalEditor.vue';

vi.mock('@focus/api-client', () => ({
    api: { settings: { get: vi.fn(() => new Promise(() => undefined)), update: vi.fn(), reset: vi.fn() } },
    errorMessage: () => '',
    isApiError: () => false,
}));

async function mountEditor(text: string) {
    const body = ref(text);
    const wrapper = mount(JournalEditor, {
        props: {
            label: 'Entry',
            saveState: 'Saved',
            modelValue: body.value,
            'onUpdate:modelValue': (value: string) => (body.value = value),
            font: 'mono',
        },
        attachTo: document.body,
    });
    await nextTick();
    const textarea = wrapper.find('textarea').element;
    return { wrapper, body, textarea };
}

describe('JournalEditor', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        Object.defineProperty(document, 'execCommand', { configurable: true, value: () => false });
        setActivePinia(createPinia());
    });

    it('highlights the selection from the toolbar and the keyboard', async () => {
        const { wrapper, body, textarea } = await mountEditor('ship it today');
        textarea.setSelectionRange(5, 7);
        await wrapper.find('button[aria-label="Highlight"]').trigger('click');
        expect(body.value).toBe('ship ==it== today');

        textarea.setSelectionRange(0, 4);
        await wrapper.find('textarea').trigger('keydown', { key: 'b', ctrlKey: true });
        expect(body.value).toBe('**ship** ==it== today');
        wrapper.unmount();
    });

    it('inserts an emoji at the caret', async () => {
        const { wrapper, body, textarea } = await mountEditor('Good day');
        textarea.setSelectionRange(8, 8);
        await wrapper.find('button[aria-label="Emoji"]').trigger('click');
        await nextTick();
        await wrapper.find('button[aria-label="smiling"]').trigger('click');
        expect(body.value).toBe('Good day😊');
        expect(wrapper.find('#journal-emoji').exists()).toBe(false);
        wrapper.unmount();
    });
});

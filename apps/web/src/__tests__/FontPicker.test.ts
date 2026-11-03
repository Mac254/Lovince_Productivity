import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import FontPicker from '@/features/settings/FontPicker.vue';
import { usePreferencesStore } from '@/stores/preferences';

vi.mock('@focus/api-client', () => ({
    api: { settings: { get: vi.fn(), update: vi.fn(), reset: vi.fn() } },
    isApiError: () => false,
}));

const options = [
    { value: 'focus' as const, label: 'Outfit & Inter' },
    { value: 'garamond' as const, label: 'EB Garamond' },
    { value: 'fira_code' as const, label: 'Fira Code' },
];

const radios = () => [...document.body.querySelectorAll<HTMLElement>('[role="radio"]')];
const button = (label: string) =>
    [...document.body.querySelectorAll<HTMLButtonElement>('button')].find((el) => el.textContent?.trim() === label)!;

describe('FontPicker', () => {
    beforeEach(() => {
        localStorage.clear();
        document.body.innerHTML = '';
        setActivePinia(createPinia());
    });

    it('previews a typeface live and saves it only on apply', async () => {
        const wrapper = mount(FontPicker, { props: { options }, attachTo: document.body });
        const preferences = usePreferencesStore();

        await wrapper.find('button').trigger('click');
        await nextTick();
        expect(radios()).toHaveLength(3);

        radios()[1]!.click();
        await nextTick();
        expect(preferences.previewFont).toBe('garamond');
        expect(document.documentElement.dataset.font).toBe('garamond');
        expect(preferences.prefs.font).toBe('focus');

        button('Apply').click();
        await nextTick();
        expect(preferences.prefs.font).toBe('garamond');
        expect(preferences.previewFont).toBeNull();
        wrapper.unmount();
    });

    it('filters by name and drops the preview on cancel', async () => {
        const wrapper = mount(FontPicker, { props: { options }, attachTo: document.body });
        const preferences = usePreferencesStore();

        await wrapper.find('button').trigger('click');
        await nextTick();
        const search = document.body.querySelector<HTMLInputElement>('#font-search')!;
        search.value = 'fira';
        search.dispatchEvent(new Event('input'));
        await nextTick();
        expect(radios().map((el) => el.textContent?.includes('Fira Code'))).toEqual([true]);

        radios()[0]!.click();
        await nextTick();
        expect(preferences.previewFont).toBe('fira_code');

        button('Cancel').click();
        await nextTick();
        expect(preferences.previewFont).toBeNull();
        expect(preferences.prefs.font).toBe('focus');
        wrapper.unmount();
    });
});

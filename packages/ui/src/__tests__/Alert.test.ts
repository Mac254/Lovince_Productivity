import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import Tabs from '../components/Tabs/Tabs.vue';
import { usePagedList } from '../composables/usePagedList';
import Alert from '../patterns/Alert/Alert.vue';

describe('Alert', () => {
    it('interrupts for danger and stays polite otherwise', () => {
        expect(
            mount(Alert, { props: { tone: 'danger' }, slots: { default: 'Wrong password' } }).attributes('role'),
        ).toBe('alert');
        expect(mount(Alert, { slots: { default: 'Offline' } }).attributes('role')).toBe('status');
    });
});

describe('Tabs', () => {
    it('marks each trigger as a liquid item and shows only the active panel', async () => {
        const wrapper = mount(Tabs, {
            props: {
                label: 'Sections',
                tabs: [
                    { value: 'one', label: 'One' },
                    { value: 'two', label: 'Two', count: 3 },
                ],
                modelValue: 'two',
            },
            slots: { one: 'First panel', two: 'Second panel' },
        });
        await nextTick();

        const triggers = wrapper.findAll('[role="tab"]');
        expect(triggers.every((tab) => tab.attributes('data-liquid-item') !== undefined)).toBe(true);
        expect(triggers[1]?.attributes('aria-selected')).toBe('true');
        expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe('Sections');
        const visible = wrapper
            .findAll('[role="tabpanel"]')
            .filter((panel) => panel.attributes('hidden') === undefined);
        expect(visible.map((panel) => panel.text())).toEqual(['Second panel']);
    });
});

describe('usePagedList', () => {
    it('slices pages and pulls the page back when the list shrinks', async () => {
        const items = ref(Array.from({ length: 25 }, (_, index) => index));
        const list = usePagedList(items, 10);
        expect(list.lastPage).toBe(3);
        list.page = 3;
        expect(list.visible).toEqual([20, 21, 22, 23, 24]);
        items.value = items.value.slice(0, 12);
        await nextTick();
        expect(list.page).toBe(2);
        expect(list.visible).toEqual([10, 11]);
    });
});

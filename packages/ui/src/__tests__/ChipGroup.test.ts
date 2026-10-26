import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChipGroup from '../components/ChipGroup/ChipGroup.vue';

describe('ChipGroup', () => {
    it('reflects the selection with aria-pressed and updates the model', async () => {
        const wrapper = mount(ChipGroup, {
            props: {
                label: 'Status',
                modelValue: 'all',
                options: [
                    { value: 'all', label: 'All' },
                    { value: 'overdue', label: 'Overdue' },
                ],
                'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
            },
        });

        const [all, overdue] = wrapper.findAll('button');
        expect(all!.attributes('aria-pressed')).toBe('true');

        await overdue!.trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['overdue']);
        expect(overdue!.attributes('aria-pressed')).toBe('true');
    });
});

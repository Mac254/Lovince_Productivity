import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Button from '../components/Button/Button.vue';
import IconButton from '../components/IconButton/IconButton.vue';

describe('Button', () => {
    it('keeps its label and blocks activation while loading', async () => {
        const wrapper = mount(Button, { props: { loading: true }, slots: { default: 'Save' } });
        const button = wrapper.get('button');

        expect(button.text()).toContain('Save');
        expect(button.attributes('aria-busy')).toBe('true');
        expect(button.attributes('aria-disabled')).toBe('true');
    });

    it('requires an accessible name on icon buttons', () => {
        const wrapper = mount(IconButton, { props: { icon: 'close', label: 'Close dialog' } });
        expect(wrapper.get('button').attributes('aria-label')).toBe('Close dialog');
    });
});

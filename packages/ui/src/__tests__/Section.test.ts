import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h, nextTick } from 'vue';
import Section from '../patterns/Section/Section.vue';
import Table from '../components/Table/Table.vue';
import TableBody from '../components/Table/TableBody.vue';
import TableCell from '../components/Table/TableCell.vue';
import TableHead from '../components/Table/TableHead.vue';
import TableRow from '../components/Table/TableRow.vue';

describe('Section', () => {
    it('is named and described by its title and description', async () => {
        const wrapper = mount(Section, {
            props: { title: 'Active goals', description: 'What you are working towards', level: 3 },
            slots: { default: 'Goals', actions: '<button>All goals</button>' },
        });
        await nextTick();

        const section = wrapper.get('section');
        const title = wrapper.get('h3');
        expect(title.text()).toBe('Active goals');
        expect(section.attributes('aria-labelledby')).toBe(title.attributes('id'));
        expect(wrapper.get(`#${section.attributes('aria-describedby')}`).text()).toBe('What you are working towards');
        expect(wrapper.get('header').find('button').exists()).toBe(true);
    });
});

describe('Table', () => {
    it('renders a captioned table with row headers and aligned cells', () => {
        const wrapper = mount(Table, {
            props: { caption: 'Daily numbers', label: 'Daily numbers' },
            slots: {
                default: () =>
                    h(TableBody, () =>
                        h(TableRow, () => [
                            h(TableHead, { scope: 'row' }, () => 'Mon'),
                            h(TableCell, { align: 'end', numeric: true }, () => '3'),
                        ]),
                    ),
            },
        });

        expect(wrapper.attributes('role')).toBe('region');
        expect(wrapper.attributes('tabindex')).toBe('0');
        expect(wrapper.get('caption').text()).toBe('Daily numbers');
        expect(wrapper.get('th').attributes('scope')).toBe('row');
        expect(wrapper.get('td').classes()).toEqual(expect.arrayContaining(['text-right', 'tabular']));
    });
});

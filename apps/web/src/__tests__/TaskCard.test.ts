import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TaskCard from '@/features/tasks/TaskCard.vue';
import type { Task } from '@focus/types';

const task: Task = {
    id: 7,
    title: 'Integrate portal',
    description: '<img src=x onerror=alert(1)>',
    status: 'in_progress',
    priority: 'urgent',
    category: 'integration',
    is_highlighted: false,
    start_date: null,
    due_date: '2020-01-01',
    completed_at: null,
    is_overdue: true,
    task_type_id: null,
    type: null,
    goal_id: null,
    goal: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
};

const mountCard = (props = {}) =>
    mount(TaskCard, { props: { task, ...props }, global: { stubs: { RouterLink: true } }, attachTo: document.body });

describe('TaskCard', () => {
    it('renders descriptions as text, never HTML', () => {
        const wrapper = mountCard();
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    });

    it('labels status and priority with text, not colour alone', () => {
        const text = mountCard().text();
        expect(text).toContain('In progress');
        expect(text).toContain('Urgent');
        expect(text).toContain('Integration');
        expect(text).toMatch(/overdue/);
    });

    it('shows the user’s own task type by name', () => {
        const typed = { ...task, task_type_id: 3, type: { id: 3, name: 'Study', tone: 'violet' as const } };
        expect(mountCard({ task: typed }).text()).toContain('Study');
        expect(mountCard().text()).toContain('Integration');
    });

    it('emits star from the highlight toggle', async () => {
        const wrapper = mountCard();
        await wrapper.get('button[aria-label="Highlight “Integrate portal”"]').trigger('click');
        expect(wrapper.emitted('star')?.[0]).toEqual([task]);
    });

    it('emits toggle from the accessible complete button', async () => {
        const wrapper = mountCard();
        await wrapper.get('button[aria-label="Mark “Integrate portal” complete"]').trigger('click');
        expect(wrapper.emitted('toggle')?.[0]).toEqual([task]);
    });

    it('opens an action menu that supports keyboard and emits delete', async () => {
        const wrapper = mountCard();
        const trigger = wrapper.get('button[aria-haspopup="menu"]');
        await trigger.trigger('click');
        expect(trigger.attributes('aria-expanded')).toBe('true');

        const items = wrapper.findAll('[role="menuitem"]');
        expect(items.map((item) => item.text())).toEqual(['View details', 'Edit task', 'Cancel task', 'Delete task']);

        await items[3]!.trigger('click');
        expect(wrapper.emitted('delete')?.[0]).toEqual([task]);
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        wrapper.unmount();
    });
});

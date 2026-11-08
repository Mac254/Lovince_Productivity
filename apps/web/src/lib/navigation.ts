import type { IconName, IconMotion } from '@focus/ui';

export interface NavItem {
    name: string;
    label: string;
    icon: IconName;
    motion: IconMotion;
}

export const navigation: { label: string; items: NavItem[] }[] = [
    {
        label: 'Overview',
        items: [
            { name: 'dashboard', label: 'Dashboard', icon: 'dashboard', motion: 'pop' },
            { name: 'today', label: 'Today', icon: 'calendar-check', motion: 'lift' },
            { name: 'schedules', label: 'Schedules', icon: 'calendar-clock', motion: 'nudge' },
            { name: 'suggestions', label: 'Suggestions', icon: 'lightbulb', motion: 'pop' },
        ],
    },
    {
        label: 'Work',
        items: [
            { name: 'tasks', label: 'Tasks', icon: 'tasks', motion: 'nudge' },
            { name: 'goals', label: 'Goals', icon: 'target', motion: 'spin' },
            { name: 'achievements', label: 'Achievements', icon: 'star', motion: 'pop' },
            { name: 'books', label: 'Books', icon: 'book', motion: 'lift' },
            { name: 'types', label: 'Types', icon: 'tag', motion: 'wiggle' },
            { name: 'blockers', label: 'Blockers', icon: 'blocker', motion: 'wiggle' },
        ],
    },
    {
        label: 'Money',
        items: [
            { name: 'accounting', label: 'Overview', icon: 'wallet', motion: 'pop' },
            { name: 'transactions', label: 'Transactions', icon: 'transfer', motion: 'nudge' },
            { name: 'financial-accounts', label: 'Accounts', icon: 'card', motion: 'lift' },
            { name: 'budgets', label: 'Budgets', icon: 'target', motion: 'pop' },
            { name: 'money-goals', label: 'Goals', icon: 'star', motion: 'pop' },
            { name: 'recurring', label: 'Recurring', icon: 'calendar-clock', motion: 'nudge' },
            { name: 'financial-reports', label: 'Statements', icon: 'report', motion: 'pop' },
        ],
    },
    {
        label: 'Insights',
        items: [
            { name: 'metrics', label: 'Metrics', icon: 'metrics', motion: 'lift' },
            { name: 'reports', label: 'Reports', icon: 'report', motion: 'pop' },
            { name: 'review', label: 'Weekly review', icon: 'calendar-range', motion: 'lift' },
            { name: 'journal', label: 'Journal', icon: 'journal', motion: 'wiggle' },
        ],
    },
];

import { describe, expect, it } from 'vitest';
import type { Budget, RecurringTransaction, Transaction } from '@focus/types';
import {
    budgetState,
    elapsedShare,
    flowOf,
    goalPercent,
    isAmount,
    monthlyAmount,
    monthOf,
    toDecimal,
} from '@/features/accounting/money';

describe('money', () => {
    it('turns minor units into exact decimal strings', () => {
        expect(toDecimal(150050, 'KES')).toBe('1500.50');
        expect(toDecimal(5, 'KES')).toBe('0.05');
        expect(toDecimal(-30000, 'KES')).toBe('-300.00');
        expect(toDecimal(5000, 'UGX')).toBe('5000');
        expect(toDecimal(999_999_999_999_999, 'USD')).toBe('9999999999999.99');
    });

    it('checks amounts the way the server does', () => {
        expect(isAmount('1,500.50', 'KES')).toBe(true);
        expect(isAmount('1.505', 'KES')).toBe(false);
        expect(isAmount('0', 'KES')).toBe(false);
        expect(isAmount('-5', 'KES')).toBe(false);
        expect(isAmount('5000.5', 'UGX')).toBe(false);
    });

    it('reads a transfer from the side of the account it is listed under', () => {
        const transfer = {
            type: 'transfer',
            direction: 'out',
            account: { id: 1 },
            to_account: { id: 2 },
        } as unknown as Transaction;
        expect(flowOf(transfer, 1)).toBe('out');
        expect(flowOf(transfer, 2)).toBe('in');
        expect(flowOf(transfer)).toBe('transfer');
    });

    it('finds calendar months across year ends and short months', () => {
        expect(monthOf('2026-01-31', 1)).toEqual({ from: '2026-02-01', to: '2026-02-28' });
        expect(monthOf('2026-12-15', 1)).toEqual({ from: '2027-01-01', to: '2027-01-31' });
        expect(monthOf('2028-03-10', -1)).toEqual({ from: '2028-02-01', to: '2028-02-29' });
    });

    it('measures how far through a month today is', () => {
        const november = monthOf('2026-11-04');
        expect(elapsedShare(november, '2026-10-31')).toBe(0);
        expect(elapsedShare(november, '2026-11-15')).toBe(0.5);
        expect(elapsedShare(november, '2026-11-30')).toBe(1);
        expect(elapsedShare(november, '2026-12-01')).toBe(1);
    });

    it('flags budgets that are over or spending ahead of the month', () => {
        const budget = (spent: number) => ({ amount: 10_000, spent }) as Budget;
        expect(budgetState(budget(10_001), 0.5)).toBe('over');
        expect(budgetState(budget(6_000), 0.5)).toBe('ahead');
        expect(budgetState(budget(4_000), 0.5)).toBe('on-track');
        expect(budgetState(budget(500), 0)).toBe('on-track');
        expect(budgetState(budget(1_500), 0)).toBe('ahead');
        expect(budgetState(budget(10_000), 1)).toBe('on-track');
    });

    it('spreads recurring payments over an average month', () => {
        const rule = (amount: number, frequency: RecurringTransaction['frequency']) =>
            ({ amount, frequency }) as RecurringTransaction;
        expect(monthlyAmount(rule(120_000, 'monthly'))).toBe(120_000);
        expect(monthlyAmount(rule(1_200, 'weekly'))).toBe(5_200);
        expect(monthlyAmount(rule(120_000, 'yearly'))).toBe(10_000);
    });

    it('shows goal progress as a whole percent that never passes 100', () => {
        expect(goalPercent({ saved: 0, target_amount: 6_000_000 })).toBe(0);
        expect(goalPercent({ saved: 1_999_999, target_amount: 6_000_000 })).toBe(33);
        expect(goalPercent({ saved: 7_000_000, target_amount: 6_000_000 })).toBe(100);
        expect(goalPercent({ saved: -500, target_amount: 6_000_000 })).toBe(0);
    });
});

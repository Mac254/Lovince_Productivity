import { describe, expect, it } from 'vitest';
import type { Transaction } from '@focus/types';
import { flowOf, isAmount, monthOf, toDecimal } from '@/features/accounting/money';

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
});

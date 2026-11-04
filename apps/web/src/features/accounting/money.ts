import {
    FINANCIAL_ACCOUNT_TYPES,
    LIMITS,
    TRANSACTION_TYPES,
    type Currency,
    type FinancialAccountType,
    type MinorUnits,
    type Transaction,
    type TransactionStatus,
    type TransactionType,
} from '@focus/types';
import type { BadgeTone, IconName } from '@focus/ui';
import { parseDate } from '@/lib/dates';

export function decimalsOf(currency: string): number {
    return (LIMITS.currencyDecimals as Record<string, number>)[currency] ?? 2;
}

/** Minor units as an exact decimal string ("1500.50"), built from digits so no float rounding creeps in. */
export function toDecimal(minor: MinorUnits, currency: string): string {
    const decimals = decimalsOf(currency);
    const digits = String(Math.abs(minor)).padStart(decimals + 1, '0');
    const whole = decimals ? digits.slice(0, -decimals) : digits;
    const fraction = decimals ? `.${digits.slice(-decimals)}` : '';
    return `${minor < 0 ? '-' : ''}${whole}${fraction}`;
}

export function formatMoney(minor: MinorUnits, currency: string, opts: { signed?: boolean } = {}): string {
    const decimals = decimalsOf(currency);
    const format = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        currencyDisplay: 'code',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        signDisplay: opts.signed ? 'exceptZero' : 'auto',
    });
    return format.format(toDecimal(minor, currency) as unknown as number);
}

/** Accepts what people type ("1,500.5", " 20 ") and says whether the server will take it. */
export function isAmount(text: string, currency: string): boolean {
    const decimals = decimalsOf(currency);
    const value = text.replace(/[,\s]/g, '');
    const pattern = decimals ? new RegExp(`^\\d{1,13}(\\.\\d{1,${decimals}})?$`) : /^\d{1,13}$/;
    return pattern.test(value) && /[1-9]/.test(value);
}

/** How a transaction moves the money of the account it is listed under: in, out, or between the user's own accounts. */
export function flowOf(transaction: Transaction, accountId?: number | null): 'in' | 'out' | 'transfer' {
    if (transaction.type === 'transfer') {
        if (accountId === transaction.account.id) return 'out';
        if (accountId === transaction.to_account?.id) return 'in';
        return 'transfer';
    }
    return transaction.direction;
}

export function signedAmount(transaction: Transaction, accountId?: number | null): string {
    const flow = flowOf(transaction, accountId);
    const minor = flow === 'out' ? -transaction.amount : transaction.amount;
    return formatMoney(minor, transaction.currency, { signed: flow !== 'transfer' });
}

export const transactionTypeLabels: Record<TransactionType, string> = {
    income: 'Income',
    expense: 'Expense',
    transfer: 'Transfer',
    refund: 'Refund',
    adjustment: 'Adjustment',
    interest: 'Interest',
    fee: 'Fee',
};

export const transactionStatusLabels: Record<TransactionStatus, string> = {
    pending: 'Pending',
    posted: 'Posted',
    reconciled: 'Reconciled',
    voided: 'Voided',
};

export const transactionStatusTones: Record<TransactionStatus, BadgeTone> = {
    pending: 'warning',
    posted: 'neutral',
    reconciled: 'success',
    voided: 'danger',
};

export const accountTypeLabels: Record<FinancialAccountType, string> = {
    cash: 'Cash',
    bank: 'Bank',
    mobile_money: 'Mobile money',
    savings: 'Savings',
    sacco: 'SACCO',
    money_market: 'Money market fund',
    investment: 'Investment',
    credit: 'Credit card or loan',
    other: 'Other',
};

export const accountTypeIcons: Record<FinancialAccountType, IconName> = {
    cash: 'wallet',
    bank: 'card',
    mobile_money: 'wallet',
    savings: 'target',
    sacco: 'integration',
    money_market: 'metrics',
    investment: 'metrics',
    credit: 'card',
    other: 'wallet',
};

export const transactionTypeOptions = TRANSACTION_TYPES.map((value) => ({ value, label: transactionTypeLabels[value] }));
export const accountTypeOptions = FINANCIAL_ACCOUNT_TYPES.map((value) => ({ value, label: accountTypeLabels[value] }));

export interface Period {
    from: string;
    to: string;
}

function iso(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** The calendar month containing `day`, shifted by `offset` months. */
export function monthOf(day: string, offset = 0): Period {
    const date = parseDate(day);
    const first = new Date(date.getFullYear(), date.getMonth() + offset, 1);
    const last = new Date(first.getFullYear(), first.getMonth() + 1, 0);
    return { from: iso(first), to: iso(last) };
}

export function monthLabel(period: Period): string {
    return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(parseDate(period.from));
}

export function currencyOptions(currencies: readonly Currency[]) {
    return currencies.map((value) => ({ value, label: value }));
}

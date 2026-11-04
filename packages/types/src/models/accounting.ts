import type {
    CategoryKind,
    Currency,
    FinancialAccountType,
    Frequency,
    RecurringTransactionType,
    TransactionDirection,
    TransactionStatus,
    TransactionType,
} from '../generated/contract';
import type { IsoDate, IsoDateTime } from './task';

/** Integer minor units of a currency: KES 1,500.50 is 150050. Never a float. */
export type MinorUnits = number;

export interface FinancialAccount {
    id: number;
    name: string;
    type: FinancialAccountType;
    currency: Currency;
    opening_balance: MinorUnits;
    /** Opening balance plus posted transactions, computed by the server. */
    balance: MinorUnits;
    /** Balance less pending outflows. */
    available_balance: MinorUnits;
    total_inflows: MinorUnits;
    total_outflows: MinorUnits;
    archived: boolean;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface FinancialAccountRef {
    id: number;
    name: string;
    type: FinancialAccountType;
}

export interface AccountingCategory {
    id: number;
    kind: CategoryKind;
    name: string;
    is_default: boolean;
    archived: boolean;
}

export interface Transaction {
    id: number;
    type: TransactionType;
    /** Which way money moves through `account`. */
    direction: TransactionDirection;
    status: TransactionStatus;
    amount: MinorUnits;
    currency: Currency;
    date: IsoDate;
    description: string;
    notes: string | null;
    reference: string | null;
    account: FinancialAccountRef;
    to_account: FinancialAccountRef | null;
    category: Pick<AccountingCategory, 'id' | 'name' | 'kind'> | null;
    reversal_of_id: number | null;
    reversed_by_id: number | null;
    /** The recurring payment that recorded it, if any. */
    recurring_transaction_id: number | null;
    posted_at: IsoDateTime | null;
    voided_at: IsoDateTime | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface Budget {
    id: number;
    category: { id: number; name: string; archived: boolean };
    /** The monthly limit. */
    amount: MinorUnits;
    currency: Currency;
    /** Posted spending in the month asked for, net of refunds. */
    spent: MinorUnits;
    /** Negative once the month is over budget. */
    remaining: MinorUnits;
}

export interface RecurringTransaction {
    id: number;
    type: RecurringTransactionType;
    direction: TransactionDirection;
    /** What each payment is recorded as: posted, or pending to confirm. */
    status: Extract<TransactionStatus, 'posted' | 'pending'>;
    amount: MinorUnits;
    currency: Currency;
    description: string;
    notes: string | null;
    reference: string | null;
    account: FinancialAccountRef;
    to_account: FinancialAccountRef | null;
    category: Pick<AccountingCategory, 'id' | 'name' | 'kind'> | null;
    frequency: Frequency;
    start_date: IsoDate;
    end_date: IsoDate | null;
    /** Null once the schedule has ended. */
    next_date: IsoDate | null;
    /** Payments recorded so far. */
    occurrences: number;
    paused: boolean;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

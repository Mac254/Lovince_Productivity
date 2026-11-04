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
import type { MinorUnits, RecurringTransaction, Transaction } from '../models/accounting';
import type { IsoDate } from '../models/task';

export interface AccountInput {
    name?: string;
    type?: FinancialAccountType;
    currency?: Currency;
    /** A decimal string such as "1500.50"; the server converts it to minor units. */
    opening_balance?: string;
    archived?: boolean;
}

export interface CategoryInput {
    kind?: CategoryKind;
    name?: string;
    archived?: boolean;
}

export interface TransactionInput {
    type?: TransactionType;
    account_id?: number;
    to_account_id?: number | null;
    category_id?: number | null;
    /** Only adjustments say which way they go; every other type implies it. */
    direction?: TransactionDirection | null;
    /** A decimal string such as "1500.50"; the server converts it to minor units. */
    amount?: string;
    status?: Exclude<TransactionStatus, 'voided'>;
    date?: IsoDate;
    description?: string;
    notes?: string | null;
    reference?: string | null;
}

export interface BudgetInput {
    category_id?: number;
    currency?: Currency;
    /** The monthly limit as a decimal string. */
    amount?: string;
}

export interface BudgetMonth {
    month: string;
    from: IsoDate;
    to: IsoDate;
}

export interface RecurringTransactionInput {
    type?: RecurringTransactionType;
    account_id?: number;
    to_account_id?: number | null;
    category_id?: number | null;
    /** A decimal string such as "1500.50"; the server converts it to minor units. */
    amount?: string;
    status?: 'posted' | 'pending';
    description?: string;
    notes?: string | null;
    reference?: string | null;
    frequency?: Frequency;
    start_date?: IsoDate;
    end_date?: IsoDate | null;
    paused?: boolean;
}

export interface TransactionFilters {
    search?: string;
    type?: TransactionType | '';
    status?: TransactionStatus | '';
    account_id?: number | null;
    category_id?: number | null;
    currency?: Currency | '';
    from?: IsoDate | '';
    to?: IsoDate | '';
    page?: number;
    per_page?: number;
}

export interface AccountingPeriod {
    from?: IsoDate;
    to?: IsoDate;
    currency?: Currency;
}

export interface CategoryAmount {
    id: number | null;
    name: string;
    amount: MinorUnits;
}

export type AccountingInsight =
    | { key: 'spending_change'; tone: 'warning' | 'success'; percent: number; amount: MinorUnits }
    | { key: 'top_category'; tone: 'info'; percent: number; amount: MinorUnits; category: string }
    | { key: 'negative_cash_flow'; tone: 'warning'; amount: MinorUnits }
    | { key: 'pending'; tone: 'info'; count: number; amount: MinorUnits }
    | { key: 'over_budget'; tone: 'warning'; count: number; amount: MinorUnits; category: string | null };

export interface MonthTotals {
    /** `YYYY-MM`. */
    month: string;
    from: IsoDate;
    to: IsoDate;
    income: MinorUnits;
    expenses: MinorUnits;
    net: MinorUnits;
}

export interface BudgetSummary {
    amount: MinorUnits;
    spent: MinorUnits;
    /** How many budgets are over their limit. */
    over: number;
    /** Nearest their limit first, at most five. */
    items: { id: number; category: string; amount: MinorUnits; spent: MinorUnits }[];
}

export interface AccountingDashboard {
    currency: Currency;
    currencies: Currency[];
    from: IsoDate;
    to: IsoDate;
    previous: { from: IsoDate; to: IsoDate; monthly: boolean };
    balance: MinorUnits;
    available_balance: MinorUnits;
    accounts_count: number;
    income: MinorUnits;
    expenses: MinorUnits;
    net_cash_flow: MinorUnits;
    previous_totals: { income: MinorUnits; expenses: MinorUnits; net_cash_flow: MinorUnits };
    top_expenses: CategoryAmount[];
    pending: { count: number; total: MinorUnits };
    recent: Transaction[];
    /** The six months ending with the period's month. */
    trend: MonthTotals[];
    budgets: BudgetSummary;
    /** Recurring payments due in the next two weeks. */
    upcoming: RecurringTransaction[];
    insights: AccountingInsight[];
}

interface ReportPeriod {
    currency: Currency;
    from: IsoDate;
    to: IsoDate;
}

export interface IncomeStatement extends ReportPeriod {
    income: { total: MinorUnits; categories: CategoryAmount[] };
    expenses: { total: MinorUnits; categories: CategoryAmount[] };
    net: MinorUnits;
}

export interface MoneyTrend extends ReportPeriod {
    months: MonthTotals[];
}

export interface CashFlowAccount {
    id: number;
    name: string;
    type: FinancialAccountType;
    opening: MinorUnits;
    inflows: MinorUnits;
    outflows: MinorUnits;
    transfers_in: MinorUnits;
    transfers_out: MinorUnits;
    /** Balance corrections, signed: positive added money. */
    adjustments: MinorUnits;
    closing: MinorUnits;
}

export interface CashFlow extends ReportPeriod {
    /** Money in and out of the user's accounts, transfers and adjustments left out. */
    inflows: MinorUnits;
    outflows: MinorUnits;
    net: MinorUnits;
    adjustments: MinorUnits;
    opening: MinorUnits;
    closing: MinorUnits;
    accounts: CashFlowAccount[];
}

import type { AxiosInstance } from 'axios';
import type {
    AccountInput,
    AccountingCategory,
    AccountingDashboard,
    AccountingPeriod,
    Budget,
    BudgetInput,
    BudgetMonth,
    CashFlow,
    CategoryInput,
    CategoryKind,
    FinancialAccount,
    FinancialGoal,
    FinancialGoalInput,
    GoalContribution,
    GoalContributionInput,
    IncomeStatement,
    MoneyTrend,
    Paginated,
    RecurringTransaction,
    RecurringTransactionInput,
    Resource,
    Transaction,
    TransactionFilters,
    TransactionInput,
} from '@focus/types';
import type { RequestOptions } from './types';

export function accountingEndpoints(http: AxiosInstance) {
    return {
        async dashboard(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<AccountingDashboard> {
            const { data } = await http.get<Resource<AccountingDashboard>>('/accounting/dashboard', {
                params: period,
                ...options,
            });
            return data.data;
        },
        accounts: {
            async list(
                filters: { archived?: boolean } = {},
                options: RequestOptions = {},
            ): Promise<FinancialAccount[]> {
                const params = filters.archived ? { archived: 1 } : {};
                const { data } = await http.get<Resource<FinancialAccount[]>>('/accounting/accounts', {
                    params,
                    ...options,
                });
                return data.data;
            },
            async create(input: AccountInput): Promise<FinancialAccount> {
                const { data } = await http.post<Resource<FinancialAccount>>('/accounting/accounts', input);
                return data.data;
            },
            async update(id: number, input: AccountInput): Promise<FinancialAccount> {
                const { data } = await http.patch<Resource<FinancialAccount>>(`/accounting/accounts/${id}`, input);
                return data.data;
            },
            async remove(id: number): Promise<void> {
                await http.delete(`/accounting/accounts/${id}`);
            },
        },
        categories: {
            async list(
                filters: { kind?: CategoryKind; archived?: boolean } = {},
                options: RequestOptions = {},
            ): Promise<AccountingCategory[]> {
                const params = { kind: filters.kind, archived: filters.archived ? 1 : undefined };
                const { data } = await http.get<Resource<AccountingCategory[]>>('/accounting/categories', {
                    params,
                    ...options,
                });
                return data.data;
            },
            async create(input: CategoryInput): Promise<AccountingCategory> {
                const { data } = await http.post<Resource<AccountingCategory>>('/accounting/categories', input);
                return data.data;
            },
            async update(id: number, input: CategoryInput): Promise<AccountingCategory> {
                const { data } = await http.patch<Resource<AccountingCategory>>(`/accounting/categories/${id}`, input);
                return data.data;
            },
            async remove(id: number): Promise<void> {
                await http.delete(`/accounting/categories/${id}`);
            },
        },
        transactions: {
            async list(
                filters: TransactionFilters = {},
                options: RequestOptions = {},
            ): Promise<Paginated<Transaction>> {
                const { data } = await http.get<Paginated<Transaction>>('/accounting/transactions', {
                    params: filters,
                    ...options,
                });
                return data;
            },
            /**
             * `key` identifies one submission: retrying with the same key never posts twice.
             */
            async create(input: TransactionInput, key: string): Promise<Transaction> {
                const { data } = await http.post<Resource<Transaction>>('/accounting/transactions', input, {
                    headers: { 'Idempotency-Key': key },
                });
                return data.data;
            },
            async update(id: number, input: TransactionInput): Promise<Transaction> {
                const { data } = await http.patch<Resource<Transaction>>(`/accounting/transactions/${id}`, input);
                return data.data;
            },
            async void(id: number): Promise<Transaction> {
                const { data } = await http.post<Resource<Transaction>>(`/accounting/transactions/${id}/void`);
                return data.data;
            },
            async reverse(id: number, date?: string): Promise<Transaction> {
                const { data } = await http.post<Resource<Transaction>>(
                    `/accounting/transactions/${id}/reverse`,
                    date ? { date } : {},
                );
                return data.data;
            },
            async export(filters: TransactionFilters = {}): Promise<Blob> {
                const { data } = await http.get<Blob>('/accounting/transactions/export', {
                    params: filters,
                    responseType: 'blob',
                    timeout: 60_000,
                });
                return data;
            },
        },
        budgets: {
            /** Budgets with the spending of `month` (`YYYY-MM`, this month by default). */
            async list(month?: string, options: RequestOptions = {}): Promise<{ data: Budget[]; meta: BudgetMonth }> {
                const { data } = await http.get<{ data: Budget[]; meta: BudgetMonth }>('/accounting/budgets', {
                    params: { month },
                    ...options,
                });
                return data;
            },
            async create(input: BudgetInput): Promise<Budget> {
                const { data } = await http.post<Resource<Budget>>('/accounting/budgets', input);
                return data.data;
            },
            async update(id: number, input: BudgetInput): Promise<Budget> {
                const { data } = await http.patch<Resource<Budget>>(`/accounting/budgets/${id}`, input);
                return data.data;
            },
            async remove(id: number): Promise<void> {
                await http.delete(`/accounting/budgets/${id}`);
            },
        },
        goals: {
            async list(filters: { archived?: boolean } = {}, options: RequestOptions = {}): Promise<FinancialGoal[]> {
                const params = filters.archived ? { archived: 1 } : {};
                const { data } = await http.get<Resource<FinancialGoal[]>>('/accounting/goals', { params, ...options });
                return data.data;
            },
            async create(input: FinancialGoalInput): Promise<FinancialGoal> {
                const { data } = await http.post<Resource<FinancialGoal>>('/accounting/goals', input);
                return data.data;
            },
            async update(id: number, input: FinancialGoalInput): Promise<FinancialGoal> {
                const { data } = await http.patch<Resource<FinancialGoal>>(`/accounting/goals/${id}`, input);
                return data.data;
            },
            async remove(id: number): Promise<void> {
                await http.delete(`/accounting/goals/${id}`);
            },
            contributions: {
                async list(goalId: number, options: RequestOptions = {}): Promise<GoalContribution[]> {
                    const { data } = await http.get<Resource<GoalContribution[]>>(
                        `/accounting/goals/${goalId}/contributions`,
                        options,
                    );
                    return data.data;
                },
                async create(goalId: number, input: GoalContributionInput): Promise<GoalContribution> {
                    const { data } = await http.post<Resource<GoalContribution>>(
                        `/accounting/goals/${goalId}/contributions`,
                        input,
                    );
                    return data.data;
                },
                async remove(goalId: number, id: number): Promise<void> {
                    await http.delete(`/accounting/goals/${goalId}/contributions/${id}`);
                },
            },
        },
        recurring: {
            async list(options: RequestOptions = {}): Promise<RecurringTransaction[]> {
                const { data } = await http.get<Resource<RecurringTransaction[]>>('/accounting/recurring', options);
                return data.data;
            },
            /** Saving a rule records any payment already due. */
            async create(input: RecurringTransactionInput): Promise<RecurringTransaction> {
                const { data } = await http.post<Resource<RecurringTransaction>>('/accounting/recurring', input);
                return data.data;
            },
            async update(id: number, input: RecurringTransactionInput): Promise<RecurringTransaction> {
                const { data } = await http.patch<Resource<RecurringTransaction>>(`/accounting/recurring/${id}`, input);
                return data.data;
            },
            async remove(id: number): Promise<void> {
                await http.delete(`/accounting/recurring/${id}`);
            },
        },
        reports: {
            async incomeStatement(
                period: AccountingPeriod = {},
                options: RequestOptions = {},
            ): Promise<IncomeStatement> {
                const { data } = await http.get<Resource<IncomeStatement>>('/accounting/reports/income-statement', {
                    params: period,
                    ...options,
                });
                return data.data;
            },
            /** Month by month over the period, at most 24 months ending at `to`. */
            async trend(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<MoneyTrend> {
                const { data } = await http.get<Resource<MoneyTrend>>('/accounting/reports/trend', {
                    params: period,
                    ...options,
                });
                return data.data;
            },
            async cashFlow(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<CashFlow> {
                const { data } = await http.get<Resource<CashFlow>>('/accounting/reports/cash-flow', {
                    params: period,
                    ...options,
                });
                return data.data;
            },
        },
    };
}

import type { AxiosInstance } from 'axios';
import type {
    AccountInput,
    AccountingCategory,
    AccountingDashboard,
    AccountingPeriod,
    CashFlow,
    CategoryInput,
    CategoryKind,
    FinancialAccount,
    IncomeStatement,
    Paginated,
    Resource,
    Transaction,
    TransactionFilters,
    TransactionInput,
} from '@focus/types';
import type { RequestOptions } from './types';

export function accountingEndpoints(http: AxiosInstance) {
    return {
        async dashboard(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<AccountingDashboard> {
            const { data } = await http.get<Resource<AccountingDashboard>>('/accounting/dashboard', { params: period, ...options });
            return data.data;
        },
        accounts: {
            async list(filters: { archived?: boolean } = {}, options: RequestOptions = {}): Promise<FinancialAccount[]> {
                const params = filters.archived ? { archived: 1 } : {};
                const { data } = await http.get<Resource<FinancialAccount[]>>('/accounting/accounts', { params, ...options });
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
            async list(filters: { kind?: CategoryKind; archived?: boolean } = {}, options: RequestOptions = {}): Promise<AccountingCategory[]> {
                const params = { kind: filters.kind, archived: filters.archived ? 1 : undefined };
                const { data } = await http.get<Resource<AccountingCategory[]>>('/accounting/categories', { params, ...options });
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
            async list(filters: TransactionFilters = {}, options: RequestOptions = {}): Promise<Paginated<Transaction>> {
                const { data } = await http.get<Paginated<Transaction>>('/accounting/transactions', { params: filters, ...options });
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
                const { data } = await http.post<Resource<Transaction>>(`/accounting/transactions/${id}/reverse`, date ? { date } : {});
                return data.data;
            },
            async export(filters: TransactionFilters = {}): Promise<Blob> {
                const { data } = await http.get<Blob>('/accounting/transactions/export', { params: filters, responseType: 'blob', timeout: 60_000 });
                return data;
            },
        },
        reports: {
            async incomeStatement(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<IncomeStatement> {
                const { data } = await http.get<Resource<IncomeStatement>>('/accounting/reports/income-statement', { params: period, ...options });
                return data.data;
            },
            async cashFlow(period: AccountingPeriod = {}, options: RequestOptions = {}): Promise<CashFlow> {
                const { data } = await http.get<Resource<CashFlow>>('/accounting/reports/cash-flow', { params: period, ...options });
                return data.data;
            },
        },
    };
}

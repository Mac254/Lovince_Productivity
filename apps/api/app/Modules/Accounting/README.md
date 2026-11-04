# Accounting

Personal finances: accounts, categories, transactions on a double-entry ledger, an overview and basic reports. Phase 1 of the accounting plan; budgets, savings goals, debts, recurring transactions and forecasting come later and build on the same ledger.

- Routes under `/api/v1/accounting/…`: `accounts`, `categories`, `transactions` (plus `void`, `reverse` and `export`), `dashboard`, `reports/income-statement`, `reports/cash-flow`. Records are authorised by the owner policies.
- Money is integer minor units (`Support/Money`), always with its currency. Amounts arrive as decimal strings and are converted on the server; balances and totals are never taken from the client.
- `Transaction` is what the user records. Posting one writes a `JournalEntry` whose `JournalLine`s balance: account lines debit money in and credit money out, against an income or expense nominal (a category) or, for a transfer, the other account (`Services/Ledger`).
- Entries are never changed or deleted. Editing a posted transaction cancels its entry with a mirror image on the same date and posts a new one; voiding cancels it; reversing adds an opposite transaction on its own date. Reports read only entries still in force.
- Pending transactions have no entry, so they never move a balance; an account's available balance holds back its pending outflows.
- Transfers are neither income nor spending, and are left out of consolidated cash flow. Saving is a transfer to a savings account, so there is no Savings expense category.
- Creating a transaction needs an `Idempotency-Key` header; repeating the key returns the first transaction and posts nothing. Every write runs in a database transaction and adds an `AuditLog` row.
- Default categories are created on first use (`Services/DefaultCategories`) and can be archived but not deleted.

Depends only on Users.

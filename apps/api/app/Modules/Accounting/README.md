# Accounting

Personal finances: accounts, categories, transactions on a double-entry ledger, monthly budgets, recurring payments, an overview and statements. Savings goals, debts and forecasting come later and build on the same ledger.

- Routes under `/api/v1/accounting/…`: `accounts`, `categories`, `transactions` (plus `void`, `reverse` and `export`), `budgets`, `recurring`, `dashboard`, `reports/income-statement`, `reports/cash-flow`, `reports/trend`. Records are authorised by the owner policies.
- Money is integer minor units (`Support/Money`), always with its currency. Amounts arrive as decimal strings and are converted on the server; balances and totals are never taken from the client.
- `Transaction` is what the user records. Posting one writes a `JournalEntry` whose `JournalLine`s balance: account lines debit money in and credit money out, against an income or expense nominal (a category) or, for a transfer, the other account (`Services/Ledger`).
- Entries are never changed or deleted. Editing a posted transaction cancels its entry with a mirror image on the same date and posts a new one; voiding cancels it; reversing adds an opposite transaction on its own date. Reports read only entries still in force.
- Pending transactions have no entry, so they never move a balance; an account's available balance holds back its pending outflows.
- Transfers are neither income nor spending, and are left out of consolidated cash flow. Saving is a transfer to a savings account, so there is no Savings expense category.
- Creating a transaction needs an `Idempotency-Key` header; repeating the key returns the first transaction and posts nothing. Every write runs in a database transaction and adds an `AuditLog` row.
- A budget is a monthly limit on one expense category in one currency; spending against it is read from the ledger for the month asked (`?month=YYYY-MM`), so it is never stored.
- A recurring payment is a transaction template with a weekly, monthly or yearly schedule. Each date is counted from the start date, so month ends don't drift. `accounting:post-recurring` runs every fifteen minutes and records what is due in the user's timezone with the key `recurring-{id}-{date}`, so a rerun posts nothing twice. A new rule catches up on past dates; editing the schedule or resuming carries on from today. Archiving its account pauses it.
- Default categories are created on first use (`Services/DefaultCategories`) and can be archived but not deleted.

Depends only on Users.

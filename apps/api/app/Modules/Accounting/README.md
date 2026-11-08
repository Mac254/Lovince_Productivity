# Accounting

Personal finances: accounts, categories, transactions on a double-entry ledger, monthly budgets, recurring payments, savings goals, an overview and statements. Debts and forecasting come later and build on the same ledger.

- Routes under `/api/v1/accounting/…`: `accounts`, `categories`, `transactions` (plus `void`, `reverse` and `export`), `budgets`, `recurring`, `goals` (plus `goals/{goal}/contributions`), `dashboard`, `reports/income-statement`, `reports/cash-flow`, `reports/trend`. Records are authorised by the owner policies.
- Money is integer minor units (`Support/Money`), always with its currency. Amounts arrive as decimal strings and are converted on the server; balances and totals are never taken from the client.
- `Transaction` is what the user records. Posting one writes a `JournalEntry` whose `JournalLine`s balance: account lines debit money in and credit money out, against an income or expense nominal (a category) or, for a transfer, the other account (`Services/Ledger`).
- Entries are never changed or deleted. Editing a posted transaction cancels its entry with a mirror image on the same date and posts a new one; voiding cancels it; reversing adds an opposite transaction on its own date. Reports read only entries still in force.
- Pending transactions have no entry, so they never move a balance; an account's available balance holds back its pending outflows.
- Transfers are neither income nor spending, and are left out of consolidated cash flow. Saving is a transfer to a savings account, so there is no Savings expense category.
- Creating a transaction needs an `Idempotency-Key` header; repeating the key returns the first transaction and posts nothing. Every write runs in a database transaction and adds an `AuditLog` row.
- A budget is a monthly limit on one expense category in one currency; spending against it is read from the ledger for the month asked (`?month=YYYY-MM`), so it is never stored.
- A recurring payment is a transaction template with a weekly, monthly or yearly schedule. Each date is counted from the start date, so month ends don't drift. `accounting:post-recurring` runs every fifteen minutes and records what is due in the user's timezone with the key `recurring-{id}-{date}`, so a rerun posts nothing twice. A new rule catches up on past dates; editing the schedule or resuming carries on from today. Archiving its account pauses it.
- A goal (savings, emergency fund, investment or other) has a target amount in one currency and an optional target date. What is saved is never stored (`Services/GoalProgress`): a goal linked to an account has saved that account's ledger balance, so moving money there is just a transfer; any other goal has saved the sum of the contributions the user records against it (money taken out is a negative contribution and can't take it below zero). A linked account must be open and in the goal's currency, and a goal with contributions can't be linked or change currency. The monthly amount needed spreads what is left over the months to the target date, this month included. Goals are archived rather than deleted; deleting one drops its contributions and leaves the ledger alone. The dashboard lists the open goals in its currency.
- Default categories are created on first use (`Services/DefaultCategories`) and can be archived but not deleted.

Depends only on Users.

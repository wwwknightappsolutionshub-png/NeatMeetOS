# My money (simple cash book)

Everyday money notebook for salon owners. Not tax software and not an accounting ledger.

## What it answers

- What did I take this month?
- What did I spend this month?
- What’s left?
- What’s already booked next month (rough £ from the diary)?

## Ownership

| Concern | Owner |
|---|---|
| Cash taken / spends the owner types | Money (`money_entries`) |
| Card / app collections | Payments (read-only) |
| Till sales | POS checkouts (read-only) |
| Diary value next month | Booking service lines (read-only) |

## Permissions

- `money.view` — see the notebook
- `money.manage` — add cash taken, add spend, remove a line they added

Granted to Owner. Product module `money` is on for all plans by default.

Platform can later make this a paid module without new code:

- **Per plan:** Platform → Modules → uncheck My money on Basic (keep it on Pro/Diamond).
- **Per salon:** Tenants → module overrides → turn My money off (or force on).

API permission `money.view` / `money.manage` still applies on top of the plan flag.

## Admin API (`/api/v1/admin`)

| Method | Path | Permission |
|---|---|---|
| GET | `/money/summary` | `money.view` |
| POST | `/money/entries` | `money.manage` |
| DELETE | `/money/entries/{id}` | `money.manage` |

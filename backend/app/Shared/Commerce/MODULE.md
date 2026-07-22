# Shared Commerce Contract Foundation

**Step 12** — mandatory gate before Modules 6–9.

## Documentation

See `architecture/commerce/` at repository root.

## PHP layout

| Path | Purpose |
|---|---|
| `Enums/` | Checkout, deposit, line type, allocation, event names |
| `DTO/` | Cross-module data contracts |
| `Contracts/` | Interfaces for Payments, POS, Inventory, Memberships |
| `Assemblers/` | Booking import, deposit mapping, totals, inventory intents |
| `Services/` | Eligibility, event publisher, import orchestration |
| `Models/` | Skeleton persistence (not full module implementations) |

## Booking integration

- `GET /api/v1/admin/appointments/{id}/checkout-import` — contract inspection DTO
- `appointments.billing_settlement_status` — POS settlement authority (field only; updated in Module 8)

## Module implementation order

1. Module 6 — Payments (`DepositSettlementContract` implementation, transactions)
2. Module 7 — Inventory (`StockConsumptionRequestContract` execution)
3. Module 8 — POS (`commerce_checkouts` aggregate, UI)
4. Module 9 — Memberships (`EntitlementResolutionContract` full redemption)

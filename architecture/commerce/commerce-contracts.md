# NeatMeet OS — Commerce Contracts Overview

**Step 12 — Commerce Contract Sprint**  
**Status:** Contract foundation (pre-Modules 6–9)

This pack defines the shared commerce model for Booking, Payments, Inventory, POS, and Memberships. Implementation modules **must** extend these contracts rather than invent parallel models.

---

## Ownership map

| Domain | Authoritative ownership |
|---|---|
| **Booking** | Appointments, service lines, operational lifecycle, deposit *requirements* and booking-side deposit status snapshots, walk-ins, waitlist, recurrence, rebooking links |
| **Payments** | Money movement, payment attempts/transactions, refunds, chargebacks admin, deposit collection records, allocations, payout ledger |
| **POS** | Checkout/sale aggregate, basket assembly, tenders, discounts, tips, tax snapshots, checkout completion/void, applying collected deposits |
| **Inventory** | Stock levels, movement log, consumption and reversal execution |
| **Memberships** | Entitlements, packages, wallets, loyalty balances, redemption authority and reversal ledger |

---

## Contract documents

| Document | Scope |
|---|---|
| [checkout-domain-model.md](./checkout-domain-model.md) | Sale aggregate, line types, totals, lifecycle |
| [deposit-lifecycle.md](./deposit-lifecycle.md) | Cross-module deposit states and handoffs |
| [appointment-checkout-linking.md](./appointment-checkout-linking.md) | Booking → POS import and eligibility |
| [package-membership-redemption-contracts.md](./package-membership-redemption-contracts.md) | Entitlement references and reversal |
| [inventory-consumption-contracts.md](./inventory-consumption-contracts.md) | Stock movement intents from checkout |
| [payments-ledger-contracts.md](./payments-ledger-contracts.md) | Payment concepts and allocations |
| [commerce-events-catalog.md](./commerce-events-catalog.md) | Event vocabulary and payloads |

---

## Code foundation

PHP contract layer: `backend/app/Shared/Commerce/`  
Skeleton persistence: `commerce_checkouts`, `commerce_checkout_lines`, `commerce_checkout_appointments`, `commerce_deposit_records`, `commerce_events`  
Booking bridge: `BookingCommerceBridgeService` + `BookingCheckoutImportAssembler`

---

## Rules for later modules

1. **No cross-domain table writes** — interact via services, contracts, and events.
2. **Snapshots at checkout** — prices, tax, discounts are frozen on the sale aggregate; Booking prices are inputs only.
3. **Deposit split** — Booking defines requirement; Payments records collection; POS applies credit; Memberships never owns deposit money.
4. **Settlement authority** — `billing_settlement_status` on appointments is updated by POS orchestration only.
5. **Events are facts** — `commerce_events` is append-only; analytics consumes projections later.

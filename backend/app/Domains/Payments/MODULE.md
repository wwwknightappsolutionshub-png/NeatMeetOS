# Payments domain (Module 6A)

Bounded context for payment transactions, deposit collection, refunds, and operational reporting.

## Scope (Step 13)

- Payment transactions with allocations and refund records
- Deposit collection against `commerce_deposit_records` (bridge from Booking)
- Manual payment recording and provider-agnostic payment-link placeholders
- Admin APIs and UI for transactions, deposits, refunds, and summaries
- Commerce events and audit logs for payment actions

## Ownership split

| Concern | Owner |
|---|---|
| Deposit requirement + snapshot on appointment | Booking |
| Deposit collection state (`commerce_deposit_records`) | Payments (via Shared Commerce contract) |
| Payment transactions / allocations | Payments |
| POS settlement / checkout completion | POS (deferred) |

## State model

### Payment transaction

`pending` ? `succeeded` | `failed` | `cancelled`  
`succeeded` ? `partially_refunded` | `refunded`

### Deposit bridge

Maps to `DepositLifecycleState` on `commerce_deposit_records` and `appointments.deposit_status`:

- Collect ? `collected` / `satisfied`
- Waive ? `waived` / `waived`
- Fail ? `required` / `failed`
- Refund ? `refunded` / `pending` (deposit due again)

## Admin API (`/api/v1/admin`)

| Method | Path | Permission |
|---|---|---|
| GET | `/payments` | `payments.view` |
| GET | `/payments/{id}` | `payments.view` |
| POST | `/payments/manual` | `payments.manage` |
| POST | `/payments/payment-link` | `payments.manage` |
| POST | `/payments/{id}/mark-succeeded` | `payments.manage` |
| POST | `/payments/{id}/mark-failed` | `payments.manage` |
| POST | `/payments/{id}/cancel` | `payments.manage` |
| GET | `/payments/{id}/refunds` | `payments.view` |
| POST | `/payments/{id}/refunds` | `payments.refund` |
| GET | `/payments/summary` | `payments.reporting.view` |
| GET | `/payments/failed` | `payments.reporting.view` |
| GET | `/payments/deposits` | `payments.reporting.view` |
| GET | `/appointments/{id}/deposit` | `payments.view` |
| POST | `/appointments/{id}/deposit/pay` | `payments.manage` |
| POST | `/appointments/{id}/deposit/waive` | `payments.manage` |
| POST | `/appointments/{id}/deposit/refund` | `payments.refund` |

## Services

- `PaymentTransactionService` — create manual/link transactions, status updates
- `DepositPaymentService` — deposit pay/waive/inspect, appointment sync
- `PaymentRefundService` — refunds, deposit refund bridge
- `PaymentReportingService` — summaries and failed lists
- `PaymentAllocationService` — allocation sum validation (Shared Commerce contract)

## Deferred

- Stripe Checkout / live webhooks
- Card vaulting, POS checkout UX, membership billing
- Public client payment portal, accounting sync, merchant payouts

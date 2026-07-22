# Deposit Lifecycle Contract

## Ownership split

| Concern | Owner |
|---|---|
| Whether deposit is required | Booking (`booking_services`, appointment snapshot) |
| Booking-side expectation status | Booking (`appointments.deposit_status`: `not_required`, `pending`, `satisfied`, `waived`, `failed`) |
| Money capture and ledger | Payments (`commerce_deposit_records`, future `payment_transactions`) |
| Applying deposit as checkout credit | POS (`deposit_credit` sale line) |
| Forfeiture on no-show | Payments policy (reads Booking `no_show` status) — **not implemented until Module 6** |

## Cross-module lifecycle (`DepositLifecycleState`)

| State | Set by | Meaning |
|---|---|---|
| `not_required` | Booking | No deposit expected |
| `required` | Booking | Requirement snapshot exists; not yet collected |
| `waived` | Booking | Requirement waived operationally |
| `collection_pending` | Payments | Payment link/intent created |
| `collected` | Payments | Funds captured; `commerce_deposit_records` row exists |
| `applied_to_checkout` | POS | Deposit credit line on a completed checkout |
| `refunded` | Payments | Deposit returned to client |
| `forfeited` | Payments | Retained per policy (e.g. no-show) |

## `commerce_deposit_records` (foundation)

Links appointment ↔ future payment transaction ↔ checkout application:

- `appointment_id`, `required_cents`, `collected_cents`
- `booking_deposit_status` snapshot at collection time
- `lifecycle_state` (Payments authority)
- `payment_transaction_id` (nullable until Module 6)
- `applied_checkout_id` (nullable until POS applies)

## Flows

### Collection (Module 6)

1. Booking has `deposit_status=pending` and `deposit_required_cents`
2. Payments creates `commerce_deposit_records` with `collection_pending`
3. On capture → `collected`, emit `deposit.collected`
4. Booking `deposit_status` → `satisfied` (via Payments orchestration callback)

### Checkout application (Module 8)

1. POS imports appointment with collected deposit
2. Adds `deposit_credit` line referencing `commerce_deposit_records.id`
3. On checkout complete → record `applied_to_checkout`, emit `deposit.applied`

### Cancellation / no-show (later)

- Booking sets operational `cancelled` or `no_show`
- Payments evaluates policy → `refunded` or `forfeited`
- Booking deposit_status may remain `satisfied` (money was collected); financial outcome is on deposit record

## Events

`deposit.required`, `deposit.collected`, `deposit.refunded`, `deposit.forfeited`, `deposit.applied`

# POS Domain (Modules 8A + 8B)

POS owns the checkout aggregate lifecycle for NeatMeetOS. It operates on shared commerce tables (`commerce_checkouts`, `commerce_checkout_lines`, `commerce_checkout_appointments`) and orchestrates Payments and Inventory through existing domain contracts.

## Scope (8A)

- Draft/open/completed/void checkout lifecycle
- Basket lines: appointment services, retail products, deposit credit
- Appointment import via `BookingCheckoutImportAssembler`
- Deposit credit application from collected `commerce_deposit_records`
- Split/manual tenders through Payments (`PaymentTransactionService`)
- Checkout completion: appointment billing settlement, inventory consumption, commerce events
- Admin POS UI at `/admin/pos` and `/admin/pos/[checkoutId]`

## Scope (8B)

- POS refunds (full/partial) via Payments domain with over-refund guards
- Retail line returns with inventory reversal movements
- Checkout reopen (no prior refunds, elevated permission)
- Gift card sale on completion + redemption as checkout credit/tender
- Receipt records and resend logging (delivery transport placeholder)
- Discount governance metadata (type, reason, optional authoriser)
- Completed-checkout panel: refund history, returns, reopen, receipt resend

## Ownership split

| Concern | Owner |
|---------|-------|
| Checkout aggregate, lines, completion, receipts, gift cards | POS |
| Payment transactions, allocations, refund records | Payments |
| Stock movement / reversal execution | Inventory |
| Appointment scheduling, service line source | Booking |

## API (`/api/v1/admin/pos`)

### 8A routes

| Method | Path | Permission |
|--------|------|------------|
| GET | `/pos/checkouts` | `pos.view` |
| POST | `/pos/checkouts` | `pos.manage` |
| GET | `/pos/checkouts/{id}` | `pos.view` |
| PUT | `/pos/checkouts/{id}` | `pos.manage` |
| POST | `/pos/checkouts/{id}/lines/service` | `pos.manage` |
| POST | `/pos/checkouts/{id}/lines/retail` | `pos.manage` |
| PUT | `/pos/checkouts/{id}/lines/{lineId}` | `pos.manage` |
| DELETE | `/pos/checkouts/{id}/lines/{lineId}` | `pos.manage` |
| POST | `/pos/checkouts/{id}/import-appointment` | `pos.manage` |
| POST | `/pos/checkouts/{id}/apply-deposit-credit` | `pos.manage` |
| DELETE | `/pos/checkouts/{id}/deposit-credit` | `pos.manage` |
| POST | `/pos/checkouts/{id}/payments` | `pos.manage` |
| GET | `/pos/checkouts/{id}/payments` | `pos.view` |
| POST | `/pos/checkouts/{id}/complete` | `pos.checkout.complete` |
| POST | `/pos/checkouts/{id}/void` | `pos.checkout.complete` |
| GET | `/pos/catalog/services` | `pos.view` |
| GET | `/pos/catalog/retail` | `pos.view` |
| GET | `/pos/appointments/eligible` | `pos.view` |

### 8B routes

| Method | Path | Permission |
|--------|------|------------|
| GET | `/pos/checkouts/{id}/refunds` | `pos.view` |
| POST | `/pos/checkouts/{id}/refunds` | `pos.refund` |
| POST | `/pos/checkouts/{id}/returns` | `pos.refund` |
| POST | `/pos/checkouts/{id}/reopen` | `pos.checkout.reopen` |
| POST | `/pos/checkouts/{id}/lines/gift-card` | `pos.manage` |
| POST | `/pos/checkouts/{id}/apply-gift-card` | `pos.manage` |
| DELETE | `/pos/checkouts/{id}/apply-gift-card` | `pos.manage` |
| GET | `/pos/gift-cards/{code}` | `pos.view` |
| GET | `/pos/checkouts/{id}/receipts` | `pos.view` |
| POST | `/pos/checkouts/{id}/receipts/resend` | `pos.receipt.manage` |

Recording tenders uses Payments services internally; operators need `pos.manage` on POS routes. Payments permissions still govern standalone payment admin actions.

## Permissions

- `pos.view` ù list/detail/catalog read
- `pos.manage` ù basket operations, import, deposit credit, record tenders, gift card lines/redemption
- `pos.checkout.complete` ù complete and void checkouts
- `pos.refund` ù refunds and retail returns
- `pos.checkout.reopen` ù reopen completed checkouts (no prior refunds)
- `pos.receipt.manage` ù resend receipts

Legacy aliases `commerce.pos.view` / `commerce.pos.manage` remain in the catalogue for plan/feature references.

## Services

### 8A

- `CheckoutService` ù create/list/update header
- `CheckoutLineService` ù service/retail/gift-card line CRUD
- `CheckoutImportService` ù appointment import
- `CheckoutDepositService` ù deposit credit availability/apply/remove
- `CheckoutPaymentService` ù split tender recording via Payments
- `CheckoutCompletionService` ù validation, settlement, inventory, events, gift card issue, receipt generation
- `CheckoutVoidService` ù void draft/open with no payments
- `CheckoutTotalsRecalculator` ù derived totals + amount due

### 8B

- `CheckoutRefundService` ù POS refund orchestration
- `CheckoutReturnService` ù retail returns + stock reversal
- `CheckoutReopenService` ù guarded reopen
- `GiftCardService` / `GiftCardRedemptionService` ù issue, redeem, restore on refund
- `ReceiptService` ù receipt records and resend attempts
- `CheckoutDiscountService` ù discount metadata on lines

## Completion rules

- Checkout must have at least one line
- `amount_due_cents === 0` required to complete
- Linked appointments ? `billing_settlement_status = settled`
- Collected deposit records ? `applied_checkout_id` + `applied_to_checkout` lifecycle
- Retail + service consumption rules executed synchronously on completion
- Gift card lines issue cards + ledger entries on completion
- Pending gift card redemptions finalise on completion; void before completion releases hold

## Module 9B ù membership applications

- Wallet and loyalty are not payment providers; they reduce `amount_due_cents` via Memberships ledger entries
- Totals order: subtotal ? discounts ? package coverage ? deposit ? loyalty ? wallet ? tenders
- `CheckoutMembershipApplicationService` orchestrates apply/remove; Memberships owns balances
- Reopen restores all membership applications and clears checkout fields (staff must reapply)
- Partial refund package restoration: full-line refund only in this release

## Reopen rules

- Only completed checkouts with zero refunds
- Cannot reopen if already reopened or voided
- Requires `pos.checkout.reopen`
- Re-completion runs normal validation; appointment settlement reverts to unsettled when sole settlement source

## Demo data (`PosDemoSeeder`)

After `php artisan db:seed`:

- **Scenario A** ù draft checkout + appointment `NM-POS0001` with collected deposit (import in UI)
- **Scenario B** ù completed mixed appointment + retail with deposit credit
- **Scenario C** ù completed retail-only sale
- **Scenario D** ù completed split tender (cash + card)

Login: `owner@demo.neatmeet.local` / `password` ? `/admin/pos`

## Deferred (Module 9+)

- Memberships, packages redemption, loyalty accrual (9B delivered wallet/loyalty/package at POS)
- Staff commission / chair-renter settlement
- Stripe terminal, cash drawer reconciliation, till sessions
- Public gift card purchase portal, ecommerce storefront
- Advanced tax engine, barcode scanning, accounting sync
- Actual email/SMS transport (receipt delivery uses placeholder sender)

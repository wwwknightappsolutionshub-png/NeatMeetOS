# Payments Ledger Contracts

**Authority:** Payments (Module 6)

## Core concepts (not yet persisted in full)

| Concept | Description |
|---|---|
| **Payment intent** | Client-facing obligation to pay (deposit link, checkout balance) |
| **Payment attempt** | Single try against an intent (card, cash record, etc.) |
| **Payment transaction** | Settled money record with amount, currency, status |
| **Refund** | Negative transaction linked to original |
| **Chargeback** | Dispute placeholder (admin workflow later) |
| **Payout ledger** | Provider/platform settlement (later) |

## Allocation model (`PaymentAllocationDto`)

Every captured amount is allocated:

| `allocation_type` | Target |
|---|---|
| `deposit` | `commerce_deposit_records.id` |
| `checkout_sale` | `commerce_checkouts.id` |
| `membership_subscription` | Memberships subscription invoice |
| `gift_card` | Gift card purchase/load |
| `wallet_top_up` | Wallet balance |
| `refund` | Original transaction reference |

Allocations sum to transaction amount. Split tenders = multiple transactions or split allocation rows (Module 6 decision).

## Deposit record linkage

`commerce_deposit_records.payment_transaction_id` → captured funds  
`commerce_deposit_records.applied_checkout_id` → POS credit application

## Gateway boundary

Module 6 implements providers (Stripe, etc.). This contract sprint defines **internal IDs and states only** — no gateway calls.

## Events

`payment.captured`, `payment.failed`, `refund.completed`, `chargeback.opened` (placeholders in catalog)

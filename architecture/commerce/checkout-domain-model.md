# Checkout / Sale Aggregate Contract

**Owner:** POS (Module 8)  
**Foundation table:** `commerce_checkouts`, `commerce_checkout_lines`

## Header (`CheckoutHeaderDto` / `commerce_checkouts`)

| Field | Description |
|---|---|
| `id` | Checkout UUID |
| `tenant_id` | Tenant scope |
| `client_id` | Billable client |
| `location_id` | Sale location |
| `team_member_id` | Primary provider (optional, for commission hooks) |
| `status` | `draft` → `open` → `completed` \| `voided` \| `partially_refunded` \| `fully_refunded` |
| `currency` | ISO 4217 (default tenant currency) |
| Totals snapshot | `subtotal_cents`, `discount_cents`, `tax_cents`, `tip_cents`, `total_cents` |
| `completed_at` / `voided_at` | Lifecycle timestamps |
| `metadata` | JSON extension (receipt notes, register id placeholder) |

## Line types (`SaleLineType`)

| Type | Source module | Description |
|---|---|---|
| `appointment_service` | Booking import | Billable service from appointment line |
| `retail_product` | Inventory/POS | Retail SKU sale |
| `deposit_credit` | Payments/POS | Collected deposit applied as negative line |
| `discount` | POS/Memberships | Manual or membership discount |
| `tip` | POS | Gratuity |
| `tax` | POS | VAT/tax line (may be embedded or separate) |
| `package_redemption` | Memberships | Package session redemption (amount offset) |
| `gift_card_redemption` | Payments | Gift card applied |
| `membership_discount` | Memberships | Recurring benefit discount |
| `service_fee` | POS | Booking/admin fees |

Each line carries:

- `line_type`, `description`, `quantity`, `unit_price_cents`, `line_total_cents`
- `reference_type` + `reference_id` (appointment_service_id, product_id, deposit_record_id, etc.)
- `pricing_snapshot` JSON (frozen at add-to-basket time)
- `sort_order`

## Totals model

```
subtotal_cents = sum(positive service/retail lines)
discount_cents = sum(discount + redemption + membership_discount lines, absolute)
deposit_credit_cents = sum(deposit_credit lines, absolute)
tax_cents = calculated on taxable net
tip_cents = sum(tip lines)
total_cents = subtotal - discount - deposit_credit + tax + tip
```

`CheckoutTotalsCalculator` in Shared/Commerce enforces this at contract level.

## Payment allocation

Payments attach to checkout via `PaymentAllocationDto` with `allocation_type=checkout_sale`. Multiple tenders (split) are multiple allocations. Deposits collected earlier allocate with `allocation_type=deposit` and link to `commerce_deposit_records`.

## Lifecycle

1. **draft** — basket assembly, entitlement reservation (future)
2. **open** — ready for tender (future POS UI)
3. **completed** — triggers inventory consumption intents, settlement update on appointments, `checkout.completed` event
4. **voided** — reverses entitlements and stock intents, `checkout.voided` event
5. **partially_refunded / fully_refunded** — Payments orchestrates; POS marks checkout state

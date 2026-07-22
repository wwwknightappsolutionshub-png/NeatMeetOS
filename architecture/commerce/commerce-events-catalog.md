# Commerce Events Catalog

**Store:** `commerce_events` (append-only)  
**Publisher:** `CommerceEventPublisher`

## Event envelope (`CommerceEventDto`)

| Field | Description |
|---|---|
| `event_name` | Dot-notation name (see below) |
| `tenant_id` | Tenant scope |
| `aggregate_type` | e.g. `commerce_checkout`, `appointment`, `commerce_deposit_record` |
| `aggregate_id` | UUID |
| `payload` | JSON contract per event |
| `emitted_at` | Timestamp |

Downstream: Analytics, webhooks, internal projections (not built in Step 12).

## Catalog

| Event | Emitter | Payload highlights |
|---|---|---|
| `deposit.required` | Booking | `appointment_id`, `required_cents`, `snapshot` |
| `deposit.collected` | Payments | `deposit_record_id`, `appointment_id`, `collected_cents`, `transaction_id` |
| `deposit.refunded` | Payments | `deposit_record_id`, `refund_id`, `amount_cents` |
| `deposit.forfeited` | Payments | `deposit_record_id`, `reason`, `appointment_id` |
| `deposit.applied` | POS | `deposit_record_id`, `checkout_id`, `credit_cents` |
| `checkout.created` | POS | `checkout_id`, `client_id`, `location_id` |
| `checkout.completed` | POS | `checkout_id`, `total_cents`, `appointment_ids[]`, `line_count` |
| `checkout.voided` | POS | `checkout_id`, `reason` |
| `payment.captured` | Payments | `transaction_id`, `allocations[]` |
| `refund.completed` | Payments | `refund_id`, `transaction_id`, `amount_cents` |
| `package.redeemed` | Memberships | `entitlement_id`, `checkout_line_id`, `units` |
| `package.restored` | Memberships | `entitlement_id`, `reason`, `checkout_line_id` |
| `membership.discount_applied` | Memberships | `membership_id`, `checkout_id`, `discount_cents` |
| `stock.consumed` | Inventory | `movement_id`, `product_id`, `quantity`, `checkout_line_id` |
| `stock.reversed` | Inventory | `movement_id`, `original_movement_id`, `quantity` |

## Consistency with audit logs

- **Audit logs** — operator actions (who changed what)
- **Commerce events** — business facts for commerce/analytics
- Both may fire for the same action; payloads differ

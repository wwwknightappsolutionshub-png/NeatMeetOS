# Package / Membership / Loyalty Redemption Contracts

**Authority:** Memberships (Module 9)  
**Booking role:** Reference only via `appointment_services.package_entitlement_id` and `entitlement_source`

## Entitlement reference (`EntitlementRedemptionReferenceDto`)

| Field | Owner |
|---|---|
| `entitlement_id` | Memberships |
| `entitlement_source` | Memberships (`package`, `membership`, `wallet`, `loyalty`) |
| `state` | Memberships (`referenced`, `reserved`, `redeemed`, `restored`, `expired`) |
| `appointment_service_line_id` | Booking (link point) |
| `checkout_line_id` | POS (when in basket) |

Booking **must not** decrement package balances. It may store nullable references set by Memberships or POS during checkout assembly.

## Redemption flow (future)

1. **Reference** — appointment line carries `package_entitlement_id` (optional, set at booking or checkout import)
2. **Reserve** — Memberships reserves balance when checkout is `open` (`package.redeemed` pending)
3. **Confirm** — on `checkout.completed`, Memberships marks `redeemed`
4. **Restore** — on `checkout.voided`, cancellation, or refund → `package.restored`

## Membership discount

- POS adds `membership_discount` line; Memberships validates benefit rules
- Booking is unaware of discount amounts

## Wallet / loyalty credits

- Applied as `gift_card_redemption` or dedicated wallet line type at POS
- Payments/Memberships own balance authority

## What Booking may know

- Nullable entitlement IDs on service lines
- Whether a line has an entitlement reference (read-only for UI)
- **Not** balance, expiry, or redemption ledger

## Reversal triggers

| Event | Memberships action |
|---|---|
| `checkout.voided` | Restore reserved/redeemed units |
| Appointment `cancelled` before checkout | Clear reference only; no redemption occurred |
| Refund after complete | Restore per policy |
| `no_show` | Policy-driven (forfeit session vs restore) — Module 9 + Payments |

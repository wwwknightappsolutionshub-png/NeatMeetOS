# Appointment ↔ Checkout Linking

## Cardinality

| Relationship | Rule |
|---|---|
| Checkout → Appointments | **One checkout may link to many appointments** (e.g. family, multi-provider) via `commerce_checkout_appointments` |
| Appointment → Checkouts | **One appointment may have multiple checkouts over time** (deposit-only checkout, final service checkout, void and re-checkout) |
| Active settlement | At most **one non-void `completed` checkout** may fully settle an appointment's service lines per settlement pass |

## Link table (`commerce_checkout_appointments`)

- `checkout_id`, `appointment_id`, `role` (`primary` | `additional`)
- POS creates links when importing appointments into basket

## Billing eligibility (`AppointmentCheckoutEligibilityValidator`)

| Appointment status | Checkout import |
|---|---|
| `checked_in` | ✅ Service checkout allowed |
| `completed` | ✅ Allowed (retroactive till) |
| `confirmed` / `pending` | ❌ Not yet in service |
| `cancelled` | ❌ Blocked |
| `no_show` | ❌ Blocked (deposit-only flows excepted via Payments) |
| Walk-in `waiting` | ❌ Must be seated first |

Walk-ins with `booking_source=walk_in` follow the same rules once `checked_in`.

## Import path

1. POS calls `CheckoutImportFromBookingContract::import(Appointment)`
2. `BookingCheckoutImportAssembler` produces `AppointmentCheckoutImportDto`:
   - Header context: client, location, provider, booking_reference
   - Lines: one `appointment_service` line per `appointment_services` row with price snapshot
   - Deposit hint: `DepositContractDto` from appointment snapshot
   - Entitlement hints: `package_entitlement_id` / `entitlement_source` preserved on lines
3. POS maps DTOs into `commerce_checkout_lines` (Module 8)

## Settlement authority

- `appointments.billing_settlement_status`: `not_applicable` | `unsettled` | `partially_settled` | `settled`
- **Updated only by POS** on checkout complete/void
- `appointment_services` may later reference `billed_checkout_line_id` (Module 8)

## Rebook / recurrence

- Rebooked appointments (`rebooked_from_appointment_id`) import independently; prior appointment settlement is unchanged
- Recurrence series children import like normal appointments

# Booking domain ? Module 4A + 4B + 4C

**Roadmap module:** 4 ? Booking & Scheduling (slices 4A, 4B, 4C)

## Implemented (4A)

- Service catalogue, appointments, service lines, scheduling validation, workspace-aware booking
- **Tiered display prices (extension)** — `base_price_cents` (Regular), `membership_price_cents`, `loyalty_price_cents`; online booking lets guests select a tier; Membership/Loyalty require membership PWA login (`/member/{slug}`) or CRM signup (`/join/{slug}`); `pricing_tier` stored on appointment service lines
- **Service description** — editable in admin; shown on online booking cards

## Implemented (4B)

- **Recurring series** (`appointment_recurrence_series`) ? weekly patterns with interval; generates child appointments
- **Conflict behavior:** creates valid occurrences only; reports skipped slots (does not fail entire series)
- **Waitlist** (`waitlist_entries`, `waitlist_services`) ? demand capture, status workflow, fulfilment to appointment
- **Deposit contract (Booking-owned)** ? service `deposit_required` / `deposit_amount_cents`; appointment `deposit_status`, `deposit_required_cents`, `deposit_rule_snapshot`
- **Package hooks** ? `package_entitlement_id`, `entitlement_source` on appointment service lines (nullable placeholders)
- **Booking rules scaffolding** ? `min_lead_time_hours`, `cancellation_window_hours` on services; `booking_reference` on appointments

## Implemented (4C) ? Front desk operations

- **Walk-ins** ? appointments with `booking_source=walk_in`, `walk_in_stage` (`waiting` | `seated`), `arrived_at`; waiting walk-ins do not block schedule until seated
- **Lifecycle** ? centralized transitions via `AppointmentLifecycleService`; `no_show_reason`; `correct-status` for terminal state corrections
- **Rebooking** ? `POST /appointments/{id}/rebook` copies context with `rebooked_from_appointment_id`
- **Waitlist ops** ? filters (status, location, provider, service, date); `unreachable` status; `contacted_at`
- **Workspace reassignment** ? `PATCH /appointments/{id}/workspace` with full scheduling validation
- **Day board** ? `GET /booking-board/day` with workspace occupancy summary

### Walk-in model decision

Walk-ins are **appointments** (not a parallel queue table). `walk_in_stage=waiting` holds clients in queue without provider or with provider not yet seated; seating runs scheduling validation and sets `checked_in`.

### Lifecycle rules

| From | Allowed transitions |
|---|---|
| pending | confirmed, checked_in, cancelled, no_show |
| confirmed | pending, checked_in, cancelled, no_show |
| checked_in | completed, cancelled, no_show |
| completed / cancelled / no_show | correction path only (with note) |

## Deposit ownership split

| Layer | Owns |
|---|---|
| **Booking** | Whether deposit is required, expected amount snapshot, booking-side status (`pending`, `waived`, etc.) |
| **Payments (later)** | Capture, refunds, ledger, reconciliation; may use `no_show` for forfeiture rules |

## Permissions

`booking.view`, `booking.manage` (unchanged)

## Module 9B ? package reservation

- Front-desk package reserve/release on appointment service lines (`BookingMembershipService`)
- Reserved entitlements import into POS without double redemption
- Release on cancellation or before checkout if not redeemed

## Implemented (online booking portal)

- **Public APIs** (tenant via `X-Tenant-Slug` / `X-Tenant-ID`, no Sanctum): `GET /api/v1/book/catalog`, `GET /api/v1/book/slots`, `POST /api/v1/book/appointments`, `GET /api/v1/book/appointments/{reference}?token=`, `POST /api/v1/book/appointments/{reference}/cancel`
- **Orchestration** ? `OnlineBookingService` reuses `AppointmentBookingService` + scheduling validation; creates/finds CRM clients by email; sets `booking_source=online`
- **Manage links** ? `public_manage_token` on appointments; portal `/book/[tenantSlug]/manage`
- **Post-book flow** ? confirmation + cancellation notifications via `NotificationTriggerService`; online staff alert (internal note + support email); add-to-calendar (Google + ICS) on confirmation UI
- **Frontend** ? `/book/[tenantSlug]` multi-step portal (service ? slot ? details ? confirm)
- **Admin** ? persistent sidebar includes **Book online** link to the public portal

## Deferred

- Online deposit payment capture, waitlist auto-matching / gap-fill, RRULE engine, drag-and-drop calendar, deposit forfeiture logic, real Mailgun/Twilio transport (simulation-first until Integrations)

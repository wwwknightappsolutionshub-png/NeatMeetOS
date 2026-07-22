# Memberships Domain (Module 9A)

Memberships owns recurring plan catalogues, client subscriptions, wallet credit ledger, loyalty points ledger, and prepaid package entitlements for NeatMeetOS.

## Scope (9A)

- Membership plan catalogue (billing frequency, included benefits, location scope)
- Package product catalogue (prepaid quantities, service restrictions)
- Admin-managed client subscriptions (assign, pause, resume, cancel)
- Wallet ledger (manual credit/debit, membership-issued credits)
- Loyalty ledger (manual award/deduction, membership bonus posting)
- Client package balances (assign, manual redeem/restore)
- Operational summary (MRR estimate, wallet liability, package counts)
- `EntitlementResolutionContract` implementation for commerce contract compatibility

## Ownership split

| Concern | Owner |
|---------|-------|
| Plans, subscriptions, wallet, loyalty, package balances | Memberships |
| Cash/card payment transactions | Payments |
| Checkout orchestration (future redemption) | POS |
| Appointments, service line entitlement references | Booking |
| Client profile display (future) | CRM |

## Tables

- `membership_plans`, `membership_plan_locations`
- `package_products`, `package_product_services`
- `client_memberships`
- `client_wallet_entries`
- `client_loyalty_entries`
- `client_packages`, `client_package_redemptions`

## API (`/api/v1/admin/memberships`)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/memberships/summary` | `memberships.reporting.view` |
| GET/POST | `/memberships/plans` | view / manage |
| GET/PUT | `/memberships/plans/{id}` | view / manage |
| PATCH | `/memberships/plans/{id}/archive` | manage |
| GET/POST | `/memberships/packages` | view / manage |
| GET/PUT | `/memberships/packages/{id}` | view / manage |
| PATCH | `/memberships/packages/{id}/archive` | manage |
| GET/POST | `/memberships/client-memberships` | view / manage |
| GET/PUT | `/memberships/client-memberships/{id}` | view / manage |
| PATCH | `/memberships/client-memberships/{id}/pause|resume|cancel` | manage |
| GET/POST | `/memberships/wallet-entries` | view / manage |
| GET | `/memberships/clients/{clientId}/wallet` | view |
| GET/POST | `/memberships/loyalty-entries` | view / manage |
| GET | `/memberships/clients/{clientId}/loyalty` | view |
| GET/POST | `/memberships/client-packages` | view / manage |
| GET | `/memberships/client-packages/{id}` | view |
| POST | `/memberships/client-packages/{id}/redeem|restore` | manage |

## Permissions

- `memberships.view` ù read catalogues, subscriptions, ledgers
- `memberships.manage` ù create/update/archive, assign, ledger adjustments
- `memberships.reporting.view` ù summary endpoint

Owner receives all three. Manager receives view + reporting only.

## Commerce integration

- Implements `EntitlementResolutionContract` ù resolves `client_packages.id` as entitlement reference
- `client_package_redemptions` supports `checkout_id`, `appointment_id`, `booking_service_id` for future POS/Booking flows
- Step 12 DTO shapes preserved; automatic reserve/redeem at checkout deferred

## Demo data (`MembershipsDemoSeeder`)

- **Blow Dry Club** ù monthly ù65, ù10 wallet credit
- **Colour Care Membership** ù monthly ù120, 100 loyalty points
- **6 Blow Dries Pack** ù ù180, qty 6, restricted to Cut & Blow Dry
- **Colour Refresh Bundle** ù qty 3, Full Colour
- Alex Taylor ? Blow Dry Club (active) + package with 1 redemption
- Jordan Lee ? Colour Care (loyalty bonus)

Login: `owner@demo.neatmeet.local` / `password` ? `/admin/memberships`

## Module 9B ù redemption lifecycle

Package entitlements: `available ? reserved ? redeemed ? restored/released`.

- **Booking:** reserve/release against `appointment_services` via `BookingMembershipService`
- **POS:** apply wallet, loyalty, package per checkout line via `CheckoutMembershipApplicationService`
- **Import:** appointment package reservations link to checkout lines without double redemption
- **Void/reopen:** restores wallet, loyalty, and package applications; clears checkout fields (staff must reapply)
- **Refund:** full checkout restores all membership applications; full-line refund restores package quantity

### New endpoints (9B)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/memberships/clients/{clientId}/eligible-packages` | memberships.view |
| GET | `/memberships/clients/{clientId}/wallet-summary` | memberships.view |
| GET | `/memberships/clients/{clientId}/loyalty-summary` | memberships.view |
| GET/PUT | `/memberships/settings/loyalty-redemption` | view / manage |
| GET | `/appointments/{id}/eligible-packages` | booking.view |
| POST | `/appointments/{id}/service-lines/{lineId}/package-reserve` | booking.manage |
| POST | `/appointments/{id}/service-lines/{lineId}/package-release` | booking.manage |
| GET | `/pos/checkouts/{id}/membership-options` | pos.view |
| POST | `/pos/checkouts/{id}/apply-wallet` etc. | pos.manage |

## Deferred (post-9B)

- Stripe / GoCardless recurring billing (member app purchases currently settle via payment-link simulation + immediate fulfillment)
- Automatic renewal jobs, dunning, failed payment handling
- Full self-service member portal commerce beyond `/member/{slug}` P0?P3
- Automatic POS loyalty earning rules
- Membership discount engine at checkout
- Family shared memberships (giftable package codes shipped in member PWA)
- Proration, accounting sync

## Member PWA (CRM + Memberships)

Public routes under `/api/v1/member/*` (tenant slug header + portal token):

- P0: bootstrap/login/me/check-in + installable web manifest / service worker
- P1: `dashboard`, `visits`, `loyalty`
- P2: membership/package summary + upcoming appointments on dashboard; push subscription register
- P3: `purchases` (public plan/package), `gifts` create/claim from owned packages

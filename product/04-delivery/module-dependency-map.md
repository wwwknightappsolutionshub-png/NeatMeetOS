# NeatMeet OS — Module Dependency Map

## 1. Purpose of the Dependency Map

This document defines the **dependency relationships between the 13 mandatory NeatMeet OS roadmap modules**. It is the canonical reference for:

| Use | Description |
|---|---|
| **Determine build order** | Which module must exist before another can be implemented meaningfully |
| **Identify upstream prerequisites** | What foundations, contracts, and entities must be stable |
| **Prevent premature module implementation** | Gates that block false "complete" status |
| **Protect contract-first delivery** | Especially for the commerce cluster (Booking, Payments, Inventory, POS, Memberships) |

**Companion document:** `implementation-roadmap.md` — phased delivery plan and milestones.

**Binding references:** `scope-lock.md`, `domain-boundaries.md`, `data-architecture.md`, `definition-of-done.md`.

---

## 2. Module Dependency Summary Table

Implementation sequence order (not `scope-lock.md` numbering). Dependency types: **foundational**, **operational**, **commerce**, **reporting**, **integration**, **cross-cutting**.

| Order | Module | Depends On | Enables / Unblocks | Dependency Type | Notes |
|---|---|---|---|---|---|
| 1 | **Tenant / account management** | Phase 0 repo baseline | All modules | foundational | Workspaces, RBAC, renter scoping originate here |
| 2 | **Client CRM** | Tenant (1) | Booking, Marketing, Memberships, POS, Consultation, Analytics | foundational | Baseline in Phase 1; full depth before Marketing |
| 3 | **Staff, rota & compensation** | Tenant (1) | Booking, POS, Analytics; commission after POS | foundational / operational | Availability before booking; commission after POS |
| 4 | **Booking & scheduling** | Tenant (1), CRM (2), Staff (3) | POS, Payments, Consultation, Marketing, Memberships, Inventory, Analytics | operational | Workspace scheduling is mandatory, not optional |
| 5 | **Consultation & treatment records** | Tenant (1), CRM (2), Booking (4) | Analytics, CRM history | operational | Can overlap late Phase 2 |
| — | **Commerce Contract Sprint** | Booking (4) stable | Payments, Inventory, POS, Memberships | cross-cutting | M4 gate — not a module |
| 6 | **Payments & billing** | Tenant (1), CRM (2), Booking (4), M4 contracts | POS, Memberships, Booking deposits, Integrations | commerce | Rails before POS tenders |
| 7 | **Inventory** | Tenant (1), M4 contracts; Booking (4) soft | POS, Ecommerce, Analytics | commerce | Parallel with Payments after M4 |
| 8 | **POS & checkout** | Tenant (1), CRM (2), Booking (4), Payments (6), Inventory (7), M4 | Memberships, Staff commission, Analytics, Integrations | commerce | Convergence module — not late isolated layer |
| 9 | **Memberships / loyalty / packages** | CRM (2), Payments (6), POS (8), Booking (4), M4 | Marketing, Booking packages, POS redemption | commerce | After POS for earn/redeem loop |
| 10 | **Marketing automation** | CRM (2), Booking (4); Memberships (9) soft | CRM comms history, Analytics | operational | Consent from CRM is hard gate |
| 11 | **Retail ecommerce** | Inventory (7), Payments (6); POS (8) soft | Integrations, Analytics | commerce | Extends in-salon commerce online |
| 12 | **Analytics & BI** | Events from modules 1–11 | Integrations BI export | reporting | Event-driven — not raw transactional scans |
| 13 | **Integrations** | Stable APIs/events from 1–12 | External systems | integration | Last — contracts must be frozen |

---

## 3. Detailed Dependency Notes by Module

### 1. Tenant / account management

**Needs before meaningful build:** Phase 0 — Laravel app, Sanctum, PostgreSQL, `TenantScope` pattern.

**Produces for downstream:** `Tenant`, `Location`, `Workspace`, `User`, `TeamMember`, `Role`, `Permission`, `AuditLog`, subscription plan enforcement, branding.

**NeatMeet OS constraints:**

- **Organization / location / workspace setup** — workspaces typed as `chair`, `room`, `station`, `seat`, `slot`
- **Permissions** — role templates for solo, boutique, hybrid (employees + renters), multi-location
- **Freelancer / chair-renter / room-renter support** — `employment_type` on team members; workspace assignments; location-scoped and workspace-scoped policies

**Partial build safe?** No — must be complete per DoD before Module 2 starts.

---

### 2. Client CRM

**Needs:** Tenant (1) for scoping.

**Produces:** `Client`, notes, formulas, photos, documents, tags, segments, `ConsentRecord`, communication history.

**NeatMeet OS constraints:** Shared tenant client pool with policy-scoped visibility for renters. Consent gates Marketing.

**Partial build safe?** Yes — baseline profiles in Phase 1; full CRM in Phase 2 before Marketing.

---

### 3. Staff, rota & compensation

**Needs:** Tenant (1).

**Produces:** `StaffProfile`, schedules, availability, time off; later commission, payroll exports, chair-rent billing.

**NeatMeet OS constraints:**

- **Employee / freelancer / renter models** — `employment_type` drives permissions and compensation
- **Commission** — requires POS `OrderPaid` events (Phase 4)
- **Chair-rent billing** — uses revenue/read models; not blocking booking

**Partial build safe?** Yes — availability/rota in Phase 2; commission in Phase 4 after POS.

---

### 4. Booking & scheduling

**Needs:**

- Tenant (1) — locations, workspaces, permissions
- CRM (2) — `client_id` attachment
- Staff (3) — availability, staff calendars

**Produces:** `Appointment`, `AppointmentService`, waitlist, walk-ins, recurring series, booking rules, `BookingDepositRequirement`.

**NeatMeet OS constraints:**

- **Staff + workspace + client foundations** — all three required for hybrid/chair-renter scheduling
- **Deposits handoff to Payments** — Booking owns requirement; Payments owns capture (Module 6)
- **Links to POS** — `AppointmentCompleted` → service checkout
- **Links to Memberships** — package balance check on booking
- **Links to Consultation** — appointment-linked forms
- **Links to Inventory** — `AppointmentCompleted` → consumption via rules
- **Links to Analytics** — `AppointmentConfirmed`, `AppointmentCompleted`, no-show events

**Partial build safe?** Yes for calendar/rules without deposits; **deposits sub-capability incomplete** until Payments wired.

---

### 5. Consultation & treatment records

**Needs:** CRM (2), Booking (4) for appointment context.

**Produces:** Forms, signatures, patch tests, contraindications, treatment plans, galleries.

**Partial build safe?** Yes — can proceed in parallel with Commerce Contract Sprint after booking baseline.

---

### 6. Payments & billing

**Needs:**

- Tenant (1), CRM (2) for client payment context
- Booking (4) for deposit requirements
- **M4 Commerce Contract Sprint** — tender/payment DTOs, idempotency rules

**Produces:** `PaymentIntent`, captures, refunds, payment links, `DepositTransaction`, subscriptions, payout ledger, chargebacks.

**NeatMeet OS constraints:**

- **Online payments, deposits, subscriptions, refunds, payout ledger** — all owned here
- **Relationship to POS** — POS initiates; Payments executes
- **Relationship to Memberships** — subscription billing, wallet top-up

**Partial build safe?** Yes for provider connection + capture before POS; full DoD requires POS integration.

---

### 7. Inventory

**Needs:** Tenant (1); M4 contracts for consumption handoffs; Booking (4) soft for service catalog linkage.

**Produces:** `Product`, `StockLevel`, `StockMovement`, `ServiceConsumptionRule`, suppliers, stocktakes.

**NeatMeet OS constraints:**

- **Retail + professional stock** — separate flags on products
- **Service-linked consumption** — rules reference booking services
- **Checkout interactions** — POS decrements on `OrderPaid`
- **Supplier management** — less coupled; can trail stock/consumption

**Partial build safe?** Yes — catalog + stock before POS; supplier PO workflows can follow.

---

### 8. POS & checkout

**Needs:**

- CRM (2), Booking (4), Payments (6), Inventory (7), M4 contracts

**Produces:** `Order`, `OrderLine`, tenders, discounts, refunds, tips, receipts.

**NeatMeet OS constraints:**

- **Convergence module** — aggregates appointment services, retail, tenders, gift cards, packages
- **Not a late isolated checkout module** — designed with commerce cluster from Phase 3
- **Depends on Payments** — no mock tenders
- **Depends on Inventory** — retail lines need stock
- **Depends on Memberships (soft at first)** — gift card/points need Module 9 for full DoD

**Partial build safe?** Service-only checkout before retail; full DoD needs inventory + memberships wiring.

---

### 9. Memberships / loyalty / packages

**Needs:** CRM (2), Payments (6), POS (8), Booking (4), M4 contracts.

**Produces:** Memberships, loyalty ledger, wallets, packages, gift cards, redemption codes.

**NeatMeet OS constraints:**

- **Wallets / credits / packages / points** — ledger owned here
- **Cannot be designed in isolation** — redemption at POS, package deduct at Booking, billing via Payments
- **CRM displays** read-model balances only

**Partial build safe?** Plan definitions yes; redemption requires POS.

---

### 10. Marketing automation

**Needs:** CRM (2) consent, Booking (4) events; Memberships (9) soft for loyalty campaigns.

**Produces:** Campaigns, templates, triggers, message sends, referrals.

**Partial build safe?** Yes — reminders after booking events; loyalty segments after Module 9.

---

### 11. Retail ecommerce

**Needs:** Inventory (7), Payments (6); Memberships (9) soft for gift cards online.

**Produces:** Storefront, cart, ecommerce orders, shipping/click-and-collect.

**Partial build safe?** Yes after commerce backbone complete.

---

### 12. Analytics & BI

**Needs:** `analytics_events` from modules 1–11.

**Produces:** Dashboards, reports, profitability models, cohorts.

**Partial build safe?** Incremental dashboards as events appear; full DoD after commerce complete.

---

### 13. Integrations

**Needs:** Stable REST APIs, event catalog, POS/Payments/Booking data models.

**Produces:** Provider adapters, webhooks, import jobs, API keys, sync logs.

**Partial build safe?** Per-connector — but module incomplete until core connectors ship per scope-lock.

---

## 4. Commerce Cluster Dependency Model

### Why shared contract planning is required

Booking, Payments, Inventory, POS, and Memberships share **transactional boundaries** that cross domain lines:

- A single checkout may involve appointment services, retail products, gift card redemption, package deduction, tax, tips, and split tenders
- A booking deposit spans requirement (Booking) and capture (Payments)
- Service completion triggers inventory consumption and POS checkout prompt
- Loyalty points earn on payment completion

Building any one module without agreed contracts forces mocks and incompatible state machines.

### Shared contracts (locked in Commerce Contract Sprint — M4)

| Contract | Domains involved |
|---|---|
| `Order` / `OrderLine` lifecycle | POS, Payments, Inventory, Memberships |
| `PaymentIntent` / capture / refund | Payments, POS, Booking |
| `DepositRequired` → `DepositTransaction` | Booking, Payments |
| `ServiceConsumptionRule` → stock decrement | Inventory, Booking, POS |
| Gift card / package redemption | Memberships, POS, Booking |
| `OrderPaid` / `OrderRefunded` events | POS, Payments, Inventory, Memberships, Staff, Analytics |
| `Money`, `TaxLine`, `Discount` DTOs | All commerce domains |

### Recommended order inside the cluster

```
M4 Commerce Contracts (gate)
        │
        ├──────────────────┬──────────────────┐
        ▼                  ▼                  │
   Payments (6)      Inventory (7)     (parallel)
        │                  │                  │
        └────────┬─────────┘                  │
                 ▼                            │
            POS (8) ◄── commerce hub          │
                 │                            │
                 ▼                            │
         Memberships (9)                      │
                 │                            │
                 ▼                            │
      Commerce wiring (4e) ◄────────────────┘
   deposits, consumption, redemption integration tests
```

### Incremental vs must-align-first

| Can build incrementally | Must align before coding |
|---|---|
| Payment provider sandbox connection | Order state machine |
| Product catalog CRUD | Tender → PaymentIntent handoff |
| POS service-only checkout (after Payments) | Deposit refund on cancel |
| Membership plan definitions | Gift card redemption API shape |
| Inventory stock levels | `OrderPaid` event payload |

---

## 5. Workspace / Freelancer Dependency Model

Workspace and freelancer concerns are **cross-cutting** — not confined to one module.

| Concern | Primary module | Flows to |
|---|---|---|
| **Tenant setup** | Tenant (1) | All modules — `tenant_id` scope |
| **Location hierarchy** | Tenant (1) | Booking, POS, Analytics (location filters) |
| **Workspace resources** | Tenant (1) defines; Booking (4) allocates | Staff (3) assignments, Analytics utilization |
| **Staff relationship types** | Tenant (1) + Staff (3) | Permissions, Booking calendars, Compensation |
| **Booking** | Booking (4) | Workspace + staff availability rules |
| **Compensation** | Staff (3) | Commission from POS; chair-rent from revenue |
| **Payments / rent billing** | Staff (3) + Payments (6) | Chair-rent invoice payment via payment links |
| **Analytics** | Analytics (12) | Utilization by workspace, renter revenue share |

### Employment types and scheduling

| Type | Workspace | Calendar | Clients | POS |
|---|---|---|---|---|
| **Solo salonist** | Implicit single workspace | Own | Own | Full |
| **Employee** | Assigned optional | Own + location | Shared pool (policy) | Per role |
| **Chair renter** | Assigned chair | Own chair + time | Own + shared (policy) | Own services |
| **Room renter** | Assigned room | Own room | Own + shared (policy) | Own services |
| **Freelancer (guest)** | Temporary slot | Slot-based | Limited | Configurable |

**Implementation rule:** Phase 1 must deliver workspace entities and employment types before Phase 2 booking. Retrofitting chair/room/slot models after booking is built is a **sequencing trap**.

---

## 6. Dependency Graph View

### Layered build order (top to bottom = build first)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 0: Platform (Phase 0) — repo, CI, auth shell, queues, storage   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼─────────────────────────────────────┐
│ LAYER 1: Tenant / Account (1) — org, location, workspace, RBAC, audit │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
              ┌─────────────────────┴─────────────────────┐
              ▼                                           ▼
┌──────────────────────────┐              ┌──────────────────────────────┐
│ LAYER 2a: Client CRM (2) │              │ LAYER 2b: Staff/Rota (3)     │
│ baseline → full          │              │ availability → commission    │
└────────────┬─────────────┘              └──────────────┬───────────────┘
             │                                           │
             └──────────────────┬────────────────────────┘
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 3: Booking (4) + Consultation (5) — scheduling & clinical       │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼─────────────────────────────────────┐
│ LAYER 3.5: COMMERCE CONTRACT SPRINT (M4) — shared DTOs, events, APIs    │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
              ┌─────────────────────┴─────────────────────┐
              ▼                                           ▼
┌──────────────────────────┐              ┌──────────────────────────────┐
│ LAYER 4a: Payments (6)   │              │ LAYER 4b: Inventory (7)      │
└────────────┬─────────────┘              └──────────────┬───────────────┘
             │                                           │
             └──────────────────┬────────────────────────┘
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 4c: POS (8) — commerce convergence hub                          │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 4d: Memberships (9) + commerce wiring                             │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 5: Marketing (10)                                                 │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 6: Ecommerce (11) │ Analytics (12) │ Integrations (13)          │
│ (parallel tracks)                                                       │
└─────────────────────────────────────────────────────────────────────────┘
```

### Commerce cluster flow (runtime)

```
Client books appointment (Booking)
        │
        ├─► DepositRequired ──► Payments (capture)
        │
        ▼
Appointment completed (Booking)
        │
        ├─► Inventory (consume professional stock)
        │
        ▼
POS opens service order
        │
        ├─► Memberships (redeem package / gift card / earn points)
        │
        ▼
Payments captures tender(s)
        │
        ├─► Staff (commission, tips)
        ├─► Analytics (events)
        └─► Marketing (review request trigger)
```

---

## Implementation Gate Checklist

Before starting module **N**:

1. All **hard dependencies** in the summary table are DoD-complete
2. **M4 commerce contracts** signed if N is module 6, 7, 8, or 9
3. Domain events match `data-architecture.md` catalog
4. Tenant isolation and RBAC patterns from Module 1 reused — not reimplemented
5. Workspace/renter scoping verified if module touches scheduling, POS, or compensation

---

## Related Documents

| Document | Purpose |
|---|---|
| `implementation-roadmap.md` | Phased delivery plan and milestones |
| `scope-lock.md` | Sub-capability checklist per module |
| `../../architecture/domain-boundaries.md` | Bounded context ownership |
| `../../architecture/data-architecture.md` | Event catalog and entity clusters |

---

*NeatMeet OS module dependency map — canonical build-order reference.*

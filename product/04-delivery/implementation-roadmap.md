# NeatMeet OS — Implementation Roadmap

## 1. Purpose of the Roadmap

This document is the **implementation sequencing blueprint** for NeatMeet OS. It translates the 13 mandatory roadmap modules (`scope-lock.md`), domain boundaries (`domain-boundaries.md`), and Step 2 architecture decisions into a phased delivery plan that future build steps — human and AI-assisted — must follow.

This roadmap exists to:

| Goal | How this document helps |
|---|---|
| **Preserve module ordering discipline** | Numbered module order and phase gates prevent ad-hoc feature delivery |
| **Reduce rework** | Upstream foundations (tenancy, workspaces, staff relationships) are built before dependents |
| **Protect cross-module contracts** | Commerce Contract Sprint locks shared interfaces before commerce code diverges |
| **Support AI-assisted development in controlled increments** | Each phase has clear entry criteria, deliverables, and milestone gates for Cursor agents |
| **Ensure commerce-critical modules are not built as isolated silos** | Booking, Payments, Inventory, POS, and Memberships are planned as a connected commerce cluster |

**Binding references:** `scope-lock.md`, `module-dependency-map.md`, `definition-of-done.md`, `ai-delivery-rules.md`, and all documents in `/architecture/`.

---

## 2. Delivery Principles

These principles govern all NeatMeet OS implementation work:

### Foundation before features

No operational module (booking, POS, marketing) ships before Module 1 (tenant, location, workspace, RBAC, audit) is complete per Definition of Done. Tenant isolation and permissions are not retrofitted.

### Bounded contexts before screens

Domain services, events, migrations, and policies are implemented before UI polish. Screens that call mock APIs are not accepted as module completion.

### Contracts before commerce execution

The **Commerce Contract Sprint** (Phase 3) must complete before Payments, Inventory, POS, or Memberships implementation begins. Shared order, tender, deposit, redemption, and event contracts are locked in writing and code stubs.

### Commerce cluster sequencing

**POS, Payments, Inventory, Memberships, and Booking** must be sequenced as a connected commerce cluster — not five independent modules. POS is the convergence point; Payments owns money movement; Booking owns deposit requirements; Inventory owns stock; Memberships owns wallets, packages, and points.

### Freelancer / workspace operating models are first-class

Solo salonists, freelancers, chair renters, room renters, hybrid salons, and multi-location groups are supported from Phase 1 onward via workspace entities, employment types, and scoped permissions — not bolted on after launch.

### No module complete until downstream integration obligations are satisfied

Example: Booking "deposits" sub-capability is incomplete until Payments capture/refund is wired. POS "gift cards" is incomplete until Memberships redemption is wired.

### Analytics consumes stable events

Analytics reads from `analytics_events` and read models — it does not drive transactional schema design. Operational modules emit events; Analytics aggregates them.

### Milestone-gated implementation

Progress is measured by milestone gates (M0–M8), not by screen count. A phase does not advance until its gate criteria pass.

---

## 3. Recommended Delivery Phases

### Phase 0 — Platform & Delivery Foundations

**Purpose:** Establish the monorepo, stack, and operational baseline so domain modules can be implemented consistently.

**Scope (not roadmap modules — engineering prerequisites):**

| Workstream | Deliverables |
|---|---|
| Monorepo scaffolding | `backend/` (Laravel 11), `frontend/` (Next.js 15), `docker-compose.yml` |
| Laravel backend foundation | Health endpoints, exception handling, base `TenantScope`, correlation ID middleware |
| Next.js frontend foundation | App layouts: `(admin)`, `(desk)`, `(provider)`, `(portal)`, `(book)`; API client; design tokens |
| Auth baseline | Sanctum staff auth, client guard skeleton, login/logout flows |
| Tenancy baseline | `ResolveTenant` middleware, tenant context in requests and queue jobs |
| Permissions baseline | Role/permission tables, policy registration pattern, plan feature middleware |
| Workspace resource foundation | `workspaces` table and model (chair/room/station/seat/slot types) |
| CI/CD baseline | GitHub Actions: `php artisan test`, `npm run build` on PR |
| Queues / storage / observability | Redis + Horizon, S3/MinIO Flysystem disk, structured logging, Sentry hook |

**Exit criteria (M0 + M1 partial):** Repo boots locally; CI green; tenant context resolves on authenticated request; health check passes.

**Modules:** None (prepares Module 1).

---

### Phase 1 — Core Business Identity & Operating Backbone

**Purpose:** Establish who operates the salon, where they work, and how access is controlled.

**Included modules / workstreams:**

| Module | Scope in this phase |
|---|---|
| **1 — Tenant / account management** | Salon accounts, locations, workspaces, branding, subscription plans, audit logs, feature flags |
| **Identity foundations** | Org/location/workspace hierarchy; team members; employment types (`owner`, `employee`, `freelancer`, `chair_renter`, `room_renter`) |
| **Staff relationship model (foundational)** | `TeamMember`, `StaffProfile` skeleton, workspace assignments, role templates per business type |
| **3 — Client CRM (baseline)** | Client profiles, contact details, consent records, tags — minimum viable for booking attachment |

**Why CRM baseline here:** Booking in Phase 2 requires attachable client records. Full CRM (formulas, segmentation, documents) expands in Phase 2.

**Why staff relationship model here:** Chair renters and freelancers need employment type and workspace assignment before scheduling.

**Exit criteria (M1):** Tenant + location + workspace CRUD; RBAC enforced on API; renter-scoped permissions tested; audit log on mutations; solo and hybrid salon seed configs work.

---

### Phase 2 — Scheduling & Client Operations Backbone

**Purpose:** Run day-to-day salon operations: schedule appointments on staff and workspaces, manage clients clinically, complete CRM depth.

**Included modules:**

| Module | Scope in this phase |
|---|---|
| **8 — Staff, rota & compensation (operational subset)** | Schedules, availability, time off — **not** full commission/payroll (Phase 4+) |
| **2 — Booking & scheduling** | Staff calendars, workspace/resource calendars, online booking, waitlist, walk-ins, recurring bookings, duration logic, rules engine, package booking linkage |
| **3 — Client CRM (completion)** | Notes, formulas, photos, documents, communication history, segmentation |
| **4 — Consultation & treatment records** | Forms, signatures, patch tests, contraindications, galleries, treatment plans, aftercare |

**Workspace / resource behavior:** Every bookable appointment may bind `staff_id`, `workspace_id`, or both per service rules. Chair/room/slot models are live — not deferred.

**Booking deposits (partial):** Booking defines `BookingDepositRequirement` and emits `DepositRequired`. Payment capture is wired in Phase 4 — booking deposits sub-capability stays **open** until then.

**Exit criteria (M2 + M3):** Book appointment with staff + workspace; online booking flow works; consultation form on appointment; CRM notes and consent enforced; cross-tenant isolation tested.

---

### Phase 3 — Commerce Contract Sprint

**Purpose:** Lock shared contracts between commerce cluster modules **before** implementation diverges.

**This is a mandatory implementation planning milestone — not a vague note.**

**Modules involved (contracts only — minimal implementation):**

- Booking & scheduling
- POS & checkout
- Payments & billing
- Inventory
- Memberships / loyalty / packages

**Shared contract areas to define and document:**

| Contract area | Owner(s) | Deliverable |
|---|---|---|
| Order / sale / checkout concepts | POS | `Order`, `OrderLine`, state machine diagram, OpenAPI stubs |
| Tender and payment state boundaries | POS + Payments | `Tender`, `PaymentIntent`, `CaptureRequest` DTOs; who initiates vs executes |
| Gift card redemption rules | Memberships + POS | Redemption API; balance check; partial redemption |
| Package redemption flows | Memberships + Booking + POS | Balance deduct on book vs checkout; event payloads |
| Deposit capture/refund handoffs | Booking + Payments | `DepositRequired` → `DepositTransaction`; cancel refund flow |
| Inventory consumption handoffs | Booking + POS + Inventory | `ServiceConsumptionRule`; `AppointmentCompleted` → stock decrement |
| Refund / void / reversal expectations | POS + Payments + Inventory | Restock rules; partial refund; commission reversal input |
| Event contracts | All commerce domains | Event catalog in `data-architecture.md` finalized with payload schemas |
| Shared value types | All | `Money`, `TaxLine`, `Discount` DTOs in `app/Support/Commerce/` |

**Sprint deliverables:**

1. OpenAPI spec for commerce endpoints (stubs)
2. PHP interfaces: `OrderServiceInterface`, `PaymentGatewayInterface`, `InventoryServiceInterface`, `MembershipRedemptionInterface`
3. State machine docs for Order, PaymentIntent, Appointment
4. Pest integration test skeletons with shared factories
5. Sign-off checklist — **M4: commerce contracts approved**

**No commerce module may begin Phase 4 implementation until M4 passes.**

---

### Phase 4 — Commerce Execution Backbone

**Purpose:** Implement money, stock, checkout, and loyalty products as a connected system.

**Internal sequence (justified):**

| Step | Module | Rationale |
|---|---|---|
| **4a** | **6 — Payments & billing** | Payment provider connection, `PaymentIntent`, capture, refund, payment links, deposit transactions, subscription billing skeleton. POS cannot complete tenders without this. Booking deposit capture unblocks here. |
| **4b** | **7 — Inventory** (parallel with 4a) | Product catalog, retail + professional stock, `ServiceConsumptionRule`, stock movements. Independent of payment rails but required before POS retail lines. |
| **4c** | **5 — POS & checkout** | Commerce hub: service lines from appointments, retail from inventory, tenders via payments, tips, tax, receipts. Requires 4a + 4b baselines. |
| **4d** | **10 — Memberships / loyalty / packages** | Wallets, points, packages, gift cards — redemption flows through POS and booking. Requires POS baseline for earn/redeem loop. |
| **4e** | **Commerce wiring** | Connect deposit handoffs, consumption events, package booking deduction, gift card at POS — cross-module integration tests |

**Why Payments before POS:** Tenders execute through Payments; mock capture creates false completion.

**Why Inventory parallel to Payments:** No hard dependency between rails and catalog; both needed before POS.

**Why POS before Memberships:** Points earn on `OrderPaid`; gift cards redeem at checkout; packages deduct at POS or booking.

**Why not isolated POS:** POS aggregates Booking + Inventory + Payments + Memberships in one transaction boundary.

**Staff compensation (commission subset):** Commission rules and entries begin after POS `OrderPaid` events exist — may start late Phase 4.

**Exit criteria (M5 + M6):** Take deposit on booking; complete service checkout; sell retail with stock decrement; redeem package; gift card at POS; refund with restock; commission entry generated.

---

### Phase 5 — Growth & Retention

**Purpose:** Re-engage clients, automate communications, drive reviews and referrals.

**Included modules:**

| Module | Scope |
|---|---|
| **9 — Marketing automation** | Email/SMS/WhatsApp-ready architecture, reminders, rebooking nudges, win-back, review requests, templates, referral programmes |
| **Marketing groundwork** | Consent-gated audiences from CRM; triggers from booking events |

**Ecommerce prep (optional parallel):** Storefront configuration research, product merchandising rules — full **11 — Retail ecommerce** ships in Phase 6.

**Dependencies:** CRM consent, booking events, POS/order events for purchase triggers; memberships for loyalty campaigns.

**Exit criteria (M7):** Appointment reminder sends; rebooking nudge fires on schedule; review request after completed appointment; referral attribution recorded.

---

### Phase 6 — Ecommerce, Analytics & Ecosystem

**Purpose:** Extend commerce online, report on operations, connect external systems.

**Split into three parallel tracks after prerequisites met:**

| Track | Module | Rationale for grouping |
|---|---|---|
| **A** | **11 — Retail ecommerce** | Needs inventory catalog + payments checkout; extends in-salon commerce online |
| **B** | **12 — Analytics & BI** | Needs stable `analytics_events` from Phases 2–5; must not block other tracks |
| **C** | **13 — Integrations** | Needs frozen internal contracts; webhooks, accounting, calendar sync, migration assistant |

**Why one phase with three tracks:** Ecommerce, Analytics, and Integrations have few cross-dependencies among themselves but all depend on commerce and operations being stable. Splitting into Phase 6a/6b/6c would imply serial dependency that does not exist — parallel tracks with separate gates is cleaner.

**Exit criteria (M8):** Online order placed; dashboard shows real revenue from events; accounting export runs; CSV import wizard works; outbound webhook delivers on `order.paid`.

---

## 4. Module-by-Module Implementation Order

| Order | Module | Phase | Why placed here | Upstream dependencies | Downstream dependents |
|---|---|---|---|---|---|
| **1** | Tenant / account management | 1 | Platform shell for all data and access | Phase 0 repo/auth baseline | All 12 other modules |
| **2** | Client CRM | 1–2 | Clients required to attach bookings; baseline in Phase 1, depth in Phase 2 | Module 1 | Booking, Marketing, Memberships, POS, Consultation, Analytics |
| **3** | Staff, rota & compensation | 2 (ops) / 4 (commission) | Availability before booking; commission after POS | Module 1 | Booking, POS, Analytics, Payments (payout context) |
| **4** | Booking & scheduling | 2 | Operational heart; workspace + staff scheduling | Modules 1, 2, 3 | POS, Payments, Consultation, Marketing, Memberships, Inventory, Analytics |
| **5** | Consultation & treatment records | 2 | Clinical layer on clients + appointments | Modules 1, 2, 4 | Analytics, CRM history |
| **—** | Commerce Contract Sprint | 3 | Lock shared commerce interfaces | Modules 4, 5 stable | Modules 6–10 commerce |
| **6** | Payments & billing | 4a | Money rails before POS tenders and deposit capture | Modules 1, 2, 4; M4 contracts | POS, Memberships, Booking deposits, Integrations, Analytics |
| **7** | Inventory | 4b | Stock model before POS retail and consumption | Module 1; M4 contracts; booking services optional | POS, Ecommerce, Analytics |
| **8** | POS & checkout | 4c | Commerce convergence hub | Modules 2, 4, 6, 7; M4 contracts | Memberships, Staff commission, Analytics, Integrations |
| **9** | Memberships / loyalty / packages | 4d | Redemption needs POS + Payments | Modules 2, 6, 8; M4 contracts | Booking, POS, Marketing, Analytics |
| **10** | Marketing automation | 5 | Campaigns need CRM, booking, consent events | Modules 2, 4; optional 9 | CRM communication history, Analytics |
| **11** | Retail ecommerce | 6A | Online store extends inventory + payments | Modules 6, 7; optional 9 | Integrations, Analytics |
| **12** | Analytics & BI | 6B | Aggregates operational events | All transactional modules emitting events | Integrations (BI export) |
| **13** | Integrations | 6C | Externalizes stable contracts | Modules 6–12 stable APIs/events | — |

*Note: Order numbers here are **implementation sequence**, not `scope-lock.md` module numbers (which differ). Cross-reference `scope-lock.md` for sub-capability checklists.*

---

## 5. Cross-Module Dependency Notes

### Tenant / Account Management ↔ all modules

Every module assumes resolved `tenant_id`, and often `location_id` and `workspace_id`. Subscription plans gate feature flags backend-enforced. Audit logs wrap all domains. Renter permission scoping originates here.

### Client CRM ↔ Booking / Marketing / Memberships / Analytics

CRM owns `Client`, consent, tags, segments. Booking references `client_id`. Marketing reads consent before send. Memberships displays loyalty on CRM profile via read model. Analytics segments retention cohorts by client data.

### Staff / Rota / Compensation ↔ Booking / Payments / POS / Analytics

Staff availability drives bookable slots. POS tip allocation and commission entries attribute to `staff_id`. Chair-rent billing uses revenue reports. Analytics tracks provider productivity.

### Booking ↔ Payments / POS / Inventory / Memberships / Consultations

| Link | Flow |
|---|---|
| Booking → Payments | `DepositRequired` event creates payment link; cancel triggers refund |
| Booking → POS | `AppointmentCompleted` opens service order; walk-in may create appointment |
| Booking → Inventory | `AppointmentCompleted` triggers service consumption |
| Booking → Memberships | Package booking deducts balance at reservation time |
| Booking → Consultation | Appointment links form submissions and treatment records |

### POS ↔ Payments / Inventory / Memberships / CRM

POS creates `Order`; Payments captures tenders; Inventory decrements on `OrderPaid`; Memberships redeems gift cards and earns points; CRM logs receipt in communication history.

### Payments ↔ Booking deposits / subscriptions / gift cards / refunds / payouts

Payments executes all money movement. Booking and POS initiate. Memberships subscriptions bill through Payments. Payout ledger reconciles provider settlements.

### Inventory ↔ POS / service consumption / retail stock / Analytics

POS retail lines check `StockLevel`. Service consumption decrements professional stock. Margin reports feed Analytics.

### Memberships ↔ CRM / Booking / POS / Payments

| Link | Flow |
|---|---|
| CRM | Display points/wallet balance |
| Booking | Package balance check on multi-session booking |
| POS | Redeem gift card, earn points on `OrderPaid` |
| Payments | Subscription renewals, wallet top-up |

### Marketing ↔ CRM / Booking / review events / retention triggers

`AppointmentConfirmed` schedules reminder. `AppointmentCompleted` triggers review request. Win-back uses `last_visit` from CRM. Consent gates all sends.

### Analytics ↔ stable events from all transactional modules

Never query raw `orders` table across domains in dashboard services — consume `analytics_events` and rollups.

### Integrations ↔ stable contracts from operational and commerce domains

Webhooks emit versioned payloads. Accounting exports read POS + Payments ledgers. Import maps to `external_id_maps`.

---

## 6. Parallelization Opportunities

| Parallel track A | Parallel track B | Prerequisite | Risk level |
|---|---|---|---|
| Client CRM baseline (Phase 1) | Workspace + RBAC (Phase 1) | Phase 0 complete | Low |
| Staff availability (Phase 2) | CRM depth (Phase 2) | Module 1 complete | Low |
| Consultation records (Phase 2) | Late booking rules refinement (Phase 2) | Booking baseline | Low |
| Payments (Phase 4a) | Inventory (Phase 4b) | M4 commerce contracts | Low — if contracts signed |
| Marketing templates (Phase 5) | Ecommerce storefront prep (Phase 6) | CRM + booking events | Medium |
| Analytics read models (Phase 6B) | Integrations adapters (Phase 6C) | Events flowing from Phase 4 | Medium |
| Retail ecommerce (Phase 6A) | Analytics (Phase 6B) | Commerce complete | Low |

**Risky parallelization (avoid):**

- POS + Memberships before Payments baseline
- Marketing sends before CRM consent model
- Integrations webhooks before event catalog frozen at M4
- Commission engine before POS `OrderPaid` events

---

## 7. Milestone Checkpoints

| Milestone | Name | Gate criteria |
|---|---|---|
| **M0** | Architecture and repo foundation approved | Step 1 + Step 2 docs complete; Phase 0 repo boots; CI green |
| **M1** | Tenancy / auth / workspace foundation complete | Module 1 DoD; renter permissions tested; audit logs verified |
| **M2** | Client + staff + booking operational baseline | Book with staff + workspace; online booking; waitlist/walk-in |
| **M3** | Consultation records and resource scheduling complete | Forms on appointment; workspace calendar allocation |
| **M4** | Commerce contracts approved | OpenAPI stubs, interfaces, event catalog, state machines signed off |
| **M5** | Payments + POS baseline complete | Deposit capture; service checkout; split tender; receipt |
| **M6** | Inventory + memberships fully connected | Retail sale + stock decrement; package redeem; gift card; consumption |
| **M7** | Growth automation baseline complete | Reminders, review requests, referral tracking live |
| **M8** | Ecommerce + analytics + integrations baseline | Online order; dashboard on events; export + import + webhook |

---

## 8. Sequencing Traps and Anti-Patterns

| Anti-pattern | Why it fails | Correct approach |
|---|---|---|
| Building POS before payment and order state contracts | Mock tenders, incompatible refund flows | Complete Phase 3 (M4) first |
| Building inventory in isolation from checkout/consumption | Stock decrements don't match POS or service completion | Define consumption contracts in Phase 3; wire in Phase 4e |
| Treating chair-renter/freelancer support as afterthought | Permission leaks, wrong calendar scope | Employment types + workspace assignments in Phase 1 |
| Implementing loyalty without POS redemption rules | Points have nowhere to earn or spend | Memberships after POS baseline |
| Implementing analytics from raw operational queries only | DB load, inconsistent metrics, breaking reports | Event ingestion from Phase 2 onward; dashboards in Phase 6 |
| Implementing integrations before internal contracts stable | Webhook churn breaks partners | Integrations last (Module 13); freeze events at M4 |
| Marking booking "deposits" complete without Payments | Money collected outside platform | Wire Booking → Payments in Phase 4a |
| Skipping Commerce Contract Sprint | Incompatible APIs between agents/teams | M4 is mandatory gate |
| Building ecommerce before in-salon POS stable | Duplicate checkout logic diverges | POS first; ecommerce extends same catalog/payments |
| Feature-drip without milestone gates | Modules "almost done" forever | DoD + milestone sign-off per phase |

---

## Related Documents

| Document | Purpose |
|---|---|
| `module-dependency-map.md` | Canonical dependency reference |
| `scope-lock.md` | Sub-capability checklist |
| `definition-of-done.md` | Module acceptance criteria |
| `../../architecture/system-architecture.md` | Technical structure |
| `../../architecture/data-architecture.md` | Event catalog and entities |

---

*NeatMeet OS implementation roadmap — binding delivery sequence for all build steps.*

# NeatMeet OS Product Charter

## Product Overview

**NeatMeet OS** is a production-grade, multi-tenant **Salon Operating System** delivered as a modular web application with optional mobile companion apps. It unifies client experience, front-desk operations, staff performance, commerce and growth, and data/integration capabilities into a single operational platform for salon businesses.

NeatMeet OS is not a booking widget, a lightweight CRM, or a point-of-sale add-on. It is a full operational system designed to run day-to-day salon business: scheduling, checkout, staff compensation, inventory, marketing, memberships, ecommerce, analytics, and third-party integrations — all under strict tenant isolation and role-based access control.

The platform is built for serious SaaS delivery: real business rules, enforced permissions, auditability, and production readiness at every layer.

### Product naming

| Context | Canonical form |
|---|---|
| Product display name | **NeatMeet OS** |
| Documentation / file slug | `neatmeet-os` |
| Repo-safe identifier (examples) | `neatmeet-os` or `neatmeet_os` depending on context |

---

## Target Users

| User segment | Primary needs |
|---|---|
| **Salon owners / operators** | Multi-location oversight, financial visibility, staff performance, growth tools, compliance |
| **Managers** | Daily operations, rota, inventory, campaigns, reporting |
| **Front-desk staff** | Calendar, POS, walk-ins, waitlist, rebooking, client lookup |
| **Service providers (stylists, therapists, barbers)** | Personal calendar, client notes, treatment records, commission visibility |
| **Clients (end customers)** | Online booking, memberships, deposits, consultations, loyalty, self-checkout |
| **Accountants / bookkeepers** | Exports, accounting sync, payout ledgers |
| **Platform administrators** | Tenant provisioning, subscription plans, system health |

---

## Supported Business Types

NeatMeet OS must accommodate the full spectrum of salon and beauty businesses:

1. **Solo salonist** — single operator, own calendar, simple POS, personal client base
2. **Chair renter** — independent practitioners within a shared location; chair-rent billing and settlement
3. **Boutique salon** — small team, mixed services, retail, local marketing
4. **Multi-location salon group** — centralized branding, per-location operations, group reporting
5. **Barber shop** — walk-in heavy, fast turnover, product retail
6. **Beauty salon / treatment studio** — consultation records, patch tests, contraindications, treatment plans, before/after galleries

Configuration and permissions must adapt to each model without requiring separate product forks.

---

## Product Goals

1. **Operational completeness** — every module in the 13-module roadmap is implemented to production standard, not as a demo shell
2. **Multi-tenant SaaS** — strict tenant isolation, subscription plans, audit logs, scalable account model
3. **Unified client journey** — from discovery and booking through treatment, checkout, loyalty, and re-engagement
4. **Staff and business performance** — rota, commission, payroll exports, productivity KPIs, owner reporting
5. **Commerce and growth** — marketing automation, memberships, ecommerce, referrals, review generation
6. **Data and integration readiness** — imports, APIs, accounting sync, payment providers, analytics warehouse, marketplace connectors
7. **Modular architecture** — clear domain boundaries, event-driven module communication, additive API evolution
8. **AI-assisted delivery discipline** — governed by scope lock, definition of done, and delivery rules documents

---

## Non-Goals

NeatMeet OS is **not**:

- A prototype, MVP demo, or superficial CRUD shell marketed as complete
- A single-purpose booking app with optional add-ons
- A generic business management tool adapted loosely for salons
- A platform that silently drops roadmap modules or substitutes them with placeholders
- A monolithic rewrite-friendly codebase without domain boundaries
- A UI-only product without backend enforcement of business rules, limits, and permissions

Extensions beyond the mandatory roadmap may be proposed but must be labeled **extension scope** and must not displace core module delivery.

---

## Five Operating Layers

NeatMeet OS is structured around five operating layers. Each layer maps to capabilities delivered across the 13 core modules.

### A. Client Experience Layer

Booking, memberships, deposits, digital consultations, loyalty, messaging, reviews, self-checkout.

*Primary modules:* Booking & scheduling, Client CRM, Consultation & treatment records, Memberships / loyalty / packages, Marketing automation, Payments & billing, POS & checkout.

### B. Front Desk / Daily Operations Layer

Calendar, multi-service appointments, room/chair/resource allocation, POS, rebooking, walk-ins, waitlist, no-show controls.

*Primary modules:* Booking & scheduling, POS & checkout, Staff / rota / compensation, Tenant / account management.

### C. Staff & Business Performance Layer

Rota, payroll / commission logic, productivity, goals, retail performance, chair-renter settlement, owner reporting.

*Primary modules:* Staff / rota / compensation, Analytics & BI, POS & checkout, Inventory.

### D. Commerce & Growth Layer

Marketing automation, campaigns, gift cards, subscriptions, referrals, e-commerce, review generation, local SEO tools.

*Primary modules:* Marketing automation, Memberships / loyalty / packages, Retail ecommerce, Integrations.

### E. Data, Migration & Integration Layer

Imports, API, accounting sync, payments, ecommerce sync, analytics warehouse, marketplace connectors, migration assistant.

*Primary modules:* Integrations, Analytics & BI, Tenant / account management.

---

## Summary of the 13 Core Modules

The NeatMeet OS roadmap consists of exactly **13 mandatory core platform modules**. No module may be omitted, partially substituted, or silently redefined. See `scope-lock.md` for the authoritative capability list.

| # | Module | Summary |
|---|---|---|
| 1 | **Tenant / account management** | Salon accounts, locations, teams, branding, permissions, subscription plans, audit logs |
| 2 | **Booking & scheduling** | Calendars, online booking, deposits, waitlist, recurring bookings, walk-ins, packages, duration logic, gap filling, rules engine |
| 3 | **Client CRM** | Profiles, notes, formulas, photos, documents, communication history, segmentation, tags, loyalty status, consent |
| 4 | **Consultation & treatment records** | Forms, digital signatures, patch tests, contraindications, galleries, treatment plans, aftercare templates |
| 5 | **POS & checkout** | Services, retail, split tenders, gift cards, discounts, refunds, tips, tax/VAT, receipts |
| 6 | **Payments & billing** | Online payments, payment links, deposits, subscriptions, payout ledger, failed payments, chargeback admin |
| 7 | **Inventory** | Retail and professional stock, suppliers, stocktakes, reorder points, movement logs, service-linked consumption |
| 8 | **Staff, rota & compensation** | Schedules, availability, time off, commission engine, payroll exports, chair-rent billing, KPIs, goals |
| 9 | **Marketing automation** | Email / SMS / WhatsApp-ready messaging, reminders, rebooking nudges, win-back, review requests, templates, referrals |
| 10 | **Memberships / loyalty / packages** | Recurring memberships, points, wallets/credits, prepaid bundles, giftable packs, balance tracking |
| 11 | **Retail ecommerce** | Online store, subscriptions, recommendations, gift cards, click-and-collect / local shipping |
| 12 | **Analytics & BI** | Dashboard, financial reports, staff performance, retention, inventory margin, booking source, profitability engine |
| 13 | **Integrations** | Accounting, payments, calendars, Google Business / Reserve with Google, ecommerce, marketing, BI exports, webhooks / API |

---

## Platform Commitments

### Multi-tenant and production-grade

NeatMeet OS is a **multi-tenant SaaS platform**. Every data query, API endpoint, background job, and integration contract must enforce tenant isolation. Subscription plans, feature flags, and usage limits are enforced at the backend — not only in the UI.

The platform is **production-grade**: migrations, service-layer business logic, authorization, audit logging, observability, error handling, and automated tests are required for module acceptance. See `definition-of-done.md`.

### Mandatory roadmap — no silent drops

The 13 modules listed above and their sub-capabilities (defined in `scope-lock.md`) constitute the **mandatory roadmap baseline**. They cannot be:

- Omitted from delivery planning
- Replaced with mock or placeholder implementations marked as complete
- Renamed or re-scoped without explicit documentation update and stakeholder acknowledgment
- Deferred indefinitely in favor of unapproved extension scope

Any work item, sprint, or AI-assisted implementation step must trace back to one or more scoped sub-capabilities within these 13 modules.

---

## Related Documents

| Document | Purpose |
|---|---|
| `scope-lock.md` | Authoritative module and sub-capability list with scope control rules |
| `../04-delivery/module-dependency-map.md` | Implementation order and module dependencies |
| `../04-delivery/implementation-roadmap.md` | Delivery phases and commerce cluster sequencing |
| `../04-delivery/definition-of-done.md` | Acceptance criteria for module completion |
| `../04-delivery/ai-delivery-rules.md` | Rules governing AI-assisted implementation |
| `../../architecture/domain-boundaries.md` | Bounded contexts and domain ownership |

---

*NeatMeet OS — Salon Operating System. This charter is the product north star for all implementation steps.*

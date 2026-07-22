# NeatMeet OS Scope Lock

## A. Scope Lock Statement

This document is the **authoritative scope baseline** for NeatMeet OS.

The **13 core platform modules** defined below are the mandatory roadmap for NeatMeet OS. They **cannot be omitted**, **partially substituted**, or **silently redefined** during design, implementation, or AI-assisted delivery.

Any implementation step that reduces, defers, or replaces a listed sub-capability without an explicit, documented scope-change decision is **out of scope for "complete" status** and must be reported as a gap.

NeatMeet OS must be built as a **production-ready operational system** — not a demo, prototype, or superficial CRUD shell. A module is not complete until every listed sub-capability for that module is implemented and passes the Definition of Done (`../04-delivery/definition-of-done.md`).

---

## B. Module List

### 1. Tenant / account management

- salon account setup
- location setup
- team setup
- branding
- permissions / roles
- subscription plans
- audit logs

### 2. Booking & scheduling

- staff calendars
- room/resource calendars
- online booking
- deposits
- waitlist
- recurring bookings
- walk-ins
- package booking
- service duration logic
- intelligent gap filling
- booking rules engine

### 3. Client CRM

- client profiles
- notes
- formulas
- photos
- documents
- communication history
- segmentation
- tags
- loyalty status
- consent / compliance history

### 4. Consultation & treatment records

- forms
- digital signatures
- patch tests
- contraindications
- before/after galleries
- treatment plans
- aftercare templates

### 5. POS & checkout

- services
- retail
- split tenders
- gift cards
- discounts
- refunds
- tips
- tax/VAT support
- receipts

### 6. Payments & billing

- online payments
- payment links
- deposits
- subscriptions
- payout ledger
- failed payment handling
- chargeback admin

### 7. Inventory

- retail stock
- professional stock
- supplier management
- stocktakes
- reorder points
- stock movement logs
- service-linked consumption

### 8. Staff, rota & compensation

- schedules
- availability
- time off
- commission engine
- payroll exports
- chair-rent billing
- productivity KPIs
- goal tracking

### 9. Marketing automation

- email / SMS / WhatsApp-ready messaging architecture if supported regionally
- push notifications (member PWA Web Push) — extension
- in-app member inbox messaging — extension
- reminders
- rebooking nudges
- win-back campaigns
- review requests
- campaign templates
- referral programmes

### 10. Memberships / loyalty / packages

- recurring memberships
- points
- wallets / credits
- prepaid bundles
- family/friend giftable packs
- package balance tracking

### 11. Retail ecommerce

- online store
- subscriptions
- retail recommendations
- gift cards
- click-and-collect / local shipping support

### 12. Analytics & BI

- dashboard
- financial reports
- staff performance
- client retention
- inventory margin
- booking source analysis
- profitability engine

### 13. Integrations

- accounting
- payments
- calendars
- Google Business / Reserve with Google
- ecommerce connectors
- marketing connectors
- BI exports
- webhooks / API

---

## C. Scope Control Rules

### Module completeness

1. **No roadmap module may be considered complete** unless **all** sub-capabilities listed for that module in Section B are implemented, tested, and accepted per the Definition of Done.
2. Sub-capability checklists are **exhaustive for the baseline roadmap**. Partial delivery within a module must be tracked explicitly as incomplete — never implied as done.

### No placeholder completion

3. **No placeholder or mock-only implementation** (UI shells, hardcoded data, fake API responses, TODO-driven flows) can be marked complete for any sub-capability or module.
4. Feature flags may gate incomplete work in non-production environments, but **flagged-off capabilities remain incomplete** until fully delivered.

### Extension scope

5. Any proposed addition **beyond** the 13 modules or their listed sub-capabilities must be labeled **"extension scope"** in planning documents, tickets, and PR descriptions.
6. Extension scope work **must not** displace, delay, or substitute mandatory roadmap delivery without an explicit documented decision.

### Ambiguity and assumptions

7. If requirements are ambiguous, **assumptions must be surfaced explicitly** in the implementation step (ticket, PR description, or decision log) before proceeding.
8. Assumptions that affect domain boundaries, data ownership, or cross-module contracts require review against `../../architecture/domain-boundaries.md`.

### Dependencies

9. **Missing dependencies must be reported** rather than guessed around. If Module N requires data or events from Module M and M is incomplete, the gap must be documented — not papered over with local mocks.
10. Cross-module integration must honor published event contracts and API boundaries defined in architecture documentation.

### Scope change process

11. Any change to this scope lock (adding, removing, or redefining sub-capabilities) requires an **explicit update to this document** with rationale and impact assessment. Silent scope drift is prohibited.

### Traceability

12. Every implementation task must reference **at least one** sub-capability from Section B by module number and capability name.
13. Delivery status reporting must be organized by module and sub-capability — not by arbitrary feature names that do not map to the roadmap.

---

## D. Delivery Principle

NeatMeet OS must be built as a **production-ready operational system**.

This means:

- Real business rules enforced in backend services, not only in UI validation
- Tenant isolation on every data access path
- Role-based permissions enforced on every mutation and sensitive read
- Audit logging on consequential changes
- Complete state machines for operational entities (bookings, orders, payments, inventory movements, etc.)
- No "demo mode" data paths in production code paths
- Automated tests covering critical workflows, permissions, and regressions
- Observability and error handling suitable for live salon operations

A module that looks complete in the UI but lacks backend enforcement, tenant isolation, or required sub-capabilities **is not complete**.

**A module cannot be marked complete unless all relevant Definition of Done sections pass** (see `../04-delivery/definition-of-done.md`).

---

*This scope lock is binding for all NeatMeet OS delivery — human and AI-assisted.*

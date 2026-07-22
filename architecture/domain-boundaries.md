# NeatMeet OS Domain Boundaries

## Purpose

This document defines the **bounded contexts** (domain boundaries) for NeatMeet OS. It establishes what each domain owns, what it does not own, how domains relate, and the core entities within each boundary.

This is not a full technical architecture document. It is the **domain map** that future implementation must follow to prevent overlap, orphaned data, and cross-module coupling.

**Rule:** A domain owns its entities and business rules. Other domains interact via published APIs, domain events, or read-model projections — not direct table access across boundaries.

**Commerce contracts (Step 12):** See [`commerce/commerce-contracts.md`](./commerce/commerce-contracts.md) for the shared checkout, deposit, payment allocation, inventory consumption, and entitlement models that bind Booking, Payments, POS, Inventory, and Memberships.

---

## Domain Map Overview

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     Identity, Tenancy & Access                           │
│  (platform shell — all domains operate within tenant context)            │
└──────────────────────────────────────────────────────────────────────────┘
         │                    │                    │
         ▼                    ▼                    ▼
┌─────────────┐      ┌─────────────┐      ┌─────────────────┐
│ Client CRM  │      │ Staff/Rota/ │      │ Inventory &     │
│             │      │ Compensation│      │ Procurement     │
└──────┬──────┘      └──────┬──────┘      └────────┬────────┘
       │                    │                      │
       └────────┬───────────┘                      │
                ▼                                   │
       ┌─────────────────┐                           │
       │ Booking &       │                           │
       │ Scheduling      │                           │
       └────────┬────────┘                           │
                │                                     │
       ┌────────┴────────┐                          │
       ▼                 ▼                          ▼
┌──────────────┐  ┌──────────────┐         ┌──────────────┐
│ Consultation │  │ Payments &   │         │ POS /        │
│ & Treatment  │  │ Billing      │◄────────│ Checkout /   │
│ Records      │  └──────┬───────┘         │ Orders       │
└──────────────┘         │                └──────┬───────┘
                         │                       │
              ┌──────────┼───────────┐           │
              ▼          ▼           ▼           ▼
       ┌──────────┐ ┌─────────┐ ┌──────────┐ ┌──────────┐
       │Membership│ │Marketing│ │ Retail   │ │Analytics │
       │/Loyalty │ │  Auto   │ │ Ecommerce│ │  & BI    │
       └──────────┘ └─────────┘ └──────────┘ └────┬─────┘
                                                   │
                                          ┌────────▼────────┐
                                          │ Integrations &  │
                                          │ Migration       │
                                          └─────────────────┘
```

---

## 1. Identity, Tenancy & Access

### Owns

- Salon accounts (tenants), subscription plans, billing account linkage
- Locations within a tenant
- Users, team membership, invitations
- Roles, permissions, policy definitions
- Branding settings (logo, colors, public-facing salon profile)
- Platform audit logs (who did what, when, on which tenant)
- Feature flags and plan-based capability enforcement

### Does not own

- Client records (Client CRM)
- Staff schedules and compensation rules (Staff domain)
- Appointment or order data (Booking, POS domains)
- Payment transactions (Payments domain)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream to all | — | Every domain requires resolved tenant context and authorization |
| Downstream | All operational domains | Consumes tenant ID, location ID, user identity, permissions |

### Core entities

`Tenant`, `Location`, `User`, `TeamMember`, `Role`, `Permission`, `SubscriptionPlan`, `AuditLog`, `BrandingSettings`

---

## 2. Booking & Scheduling

### Owns

- Appointments and appointment items (multi-service)
- Staff calendar slots and availability overlays
- Room/chair/resource calendars and allocation
- Online booking configuration and public booking flows
- Waitlist entries and walk-in queue
- Recurring booking series
- Package booking linkage
- Service duration logic and intelligent gap-filling rules
- Booking rules engine (lead time, buffers, cancellation policy enforcement)
- Deposit requirements at booking time (requirement definition; capture in Payments)

### Does not own

- Client profile data (references Client CRM)
- Staff master record and rota (references Staff domain)
- Payment capture (Payments domain)
- Treatment documentation (Consultation domain)
- POS checkout totals (POS domain)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Client CRM, Staff | Needs tenant, client, staff, resources |
| Downstream | POS, Consultation, Marketing, Analytics | Emits booking lifecycle events |
| Partner | Payments | Deposit capture and refund on cancellation |

### Core entities

`Appointment`, `AppointmentService`, `Resource`, `Availability`, `WaitlistEntry`, `WalkIn`, `BookingRule`, `RecurringSeries`, `BookingDepositRequirement`

---

## 3. Client CRM

### Owns

- Client profiles and contact details
- Client notes and formulas
- Client photos and document attachments
- Communication history log (aggregated record; send via Marketing)
- Segments, tags, and static/dynamic lists
- Loyalty status display fields owned by CRM (points balance sourced from Memberships)
- Consent records and compliance history (marketing, data processing, patch test consent)

### Does not own

- Appointment records (Booking)
- Treatment forms and clinical records (Consultation)
- Order and payment history (POS / Payments — CRM may display read-model summaries)
- Campaign execution (Marketing)
- Loyalty point ledger (Memberships)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity | Tenant-scoped client records |
| Downstream | Booking, Marketing, Memberships, Consultation, POS | Referenced by client ID |
| Consumes | Memberships, Marketing | Loyalty balance, communication events for history |

### Core entities

`Client`, `ClientNote`, `ClientFormula`, `ClientPhoto`, `ClientDocument`, `ClientTag`, `Segment`, `ConsentRecord`, `CommunicationHistoryEntry`

---

## 4. Consultations & Treatment Records

### Owns

- Consultation and intake forms (templates and instances)
- Digital signature capture and storage
- Patch test records and results
- Contraindication flags and alerts
- Before/after photo galleries (clinical context)
- Treatment plans and session notes
- Aftercare instruction templates and delivered aftercare records

### Does not own

- Client master record (Client CRM)
- Appointment scheduling (Booking — may link consultation to appointment)
- Product inventory for patch test materials (Inventory)
- Billing for treatments (POS / Payments)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Client CRM, Booking | Client and optional appointment context |
| Downstream | Analytics | Treatment completion and compliance metrics |
| Alerts | Booking, Staff | Contraindication flags may block or warn on booking |

### Core entities

`FormTemplate`, `FormSubmission`, `DigitalSignature`, `PatchTest`, `Contraindication`, `TreatmentPlan`, `TreatmentSession`, `AftercareTemplate`, `AftercareDelivery`, `BeforeAfterGallery`

---

## 5. POS / Checkout / Orders

### Owns

- Orders (sale transactions) and order line items
- Service line items linked to appointments
- Retail line items linked to inventory products
- Split tender allocation across payment methods
- Gift card redemption at checkout (balance check via Memberships/Gift Cards)
- Discount and promotion application at point of sale
- Refund records and partial refund logic
- Tip allocation to staff
- Tax/VAT calculation and line-level tax breakdown
- Receipt generation and delivery triggers

### Does not own

- Payment provider processing (Payments — POS initiates, Payments executes)
- Product catalog and stock levels (Inventory)
- Appointment state (Booking — POS may trigger check-in/complete)
- Gift card issuance ledger (Memberships)
- Commission calculation (Staff — consumes order data)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Client CRM, Booking, Inventory, Payments | All checkout inputs |
| Downstream | Staff, Analytics, Memberships, Integrations | Order events, commission input, accounting export |
| Partner | Payments | Payment capture, refund execution |

### Core entities

`Order`, `OrderLine`, `Tender`, `Discount`, `Refund`, `TipAllocation`, `TaxLine`, `Receipt`

---

## 6. Payments & Billing

### Owns

- Payment provider connections and credentials (per tenant)
- Payment intents, captures, and refunds
- Payment links (standalone collect URLs)
- Deposit transactions linked to bookings
- Subscription billing for memberships and SaaS tenant plans
- Payout ledger (provider settlements vs internal records)
- Failed payment retry and dunning state
- Chargeback records and admin workflow

### Does not own

- Order composition (POS)
- Booking deposit *requirements* (Booking defines; Payments captures)
- Membership product definition (Memberships)
- Client contact info (Client CRM)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity | Tenant payment configuration |
| Downstream | POS, Booking, Memberships, Integrations | Payment results and events |
| Partner | POS, Booking | Initiated by checkout and deposit flows |

### Core entities

`PaymentProviderConfig`, `PaymentIntent`, `PaymentCapture`, `PaymentLink`, `DepositTransaction`, `Subscription`, `PayoutLedgerEntry`, `FailedPayment`, `Chargeback`

---

## 7. Inventory & Procurement

### Owns

- Product catalog (retail and professional use flags)
- Stock levels per location
- Supplier records and purchase orders
- Stocktake sessions and adjustments
- Reorder points and low-stock alerts
- Stock movement log (receive, sell, consume, adjust, transfer)
- Service-linked product consumption rules (BOM per service)

### Does not own

- Sales transactions (POS — consumes stock)
- Ecommerce storefront presentation (Retail Ecommerce)
- Supplier payment (Payments / Integrations)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity | Tenant and location scoping |
| Downstream | POS, Retail Ecommerce, Analytics | Stock availability, margin data |
| Triggered by | POS, Booking (service completion) | Stock decrement events |

### Core entities

`Product`, `StockLevel`, `Supplier`, `PurchaseOrder`, `Stocktake`, `StockMovement`, `ReorderRule`, `ServiceConsumptionRule`

---

## 8. Staff / Rota / Compensation

### Owns

- Staff profiles (operational extension of team member)
- Work schedules and shift assignments
- Availability rules and time-off requests
- Commission rules and calculated commission entries
- Payroll export batches
- Chair-rent billing records (for renter model)
- Productivity KPI snapshots
- Staff goal definitions and progress tracking

### Does not own

- User authentication (Identity)
- Appointment assignment (Booking — references staff)
- Order totals and tips (POS — Staff consumes for commission)
- Marketing to staff (out of scope unless extension)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Booking, POS | Staff identity, appointments, sales data |
| Downstream | Analytics, Integrations | Payroll and performance exports |
| Consumed by | Booking | Availability and staff selection |

### Core entities

`StaffProfile`, `Shift`, `AvailabilityRule`, `TimeOffRequest`, `CommissionRule`, `CommissionEntry`, `PayrollExport`, `ChairRentInvoice`, `StaffKpi`, `StaffGoal`

---

## 9. Marketing Automation

### Owns

- Campaign definitions and audience selection
- Message templates (email, SMS, WhatsApp-ready architecture)
- Automated trigger rules (reminder, rebooking nudge, win-back, review request)
- Campaign send log and delivery status
- Referral programme configuration and referral attribution
- A/B variant definitions (if in scope per campaign template capability)

### Does not own

- Client master data (Client CRM — reads segments/tags/consent)
- Message transport provider credentials (Integrations or Marketing sub-config)
- Appointment data (Booking — trigger source)
- Review content storage (may link to Integrations or CRM communication history)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Client CRM, Booking, Memberships | Audience, consent, triggers |
| Downstream | Client CRM | Communication history entries |
| Partner | Integrations | Email/SMS/WhatsApp provider dispatch |

### Core entities

`Campaign`, `CampaignTemplate`, `AutomationTrigger`, `MessageSend`, `ReferralProgramme`, `ReferralAttribution`

---

## 10. Memberships / Loyalty / Packages

### Owns

- Membership plan definitions and active memberships
- Loyalty points ledger (earn, redeem, expire)
- Client wallets and store credits
- Prepaid package definitions and balance tracking
- Giftable pack purchases and redemption codes
- Family/shared pack linkage rules

### Does not own

- Client profile (Client CRM)
- Recurring payment execution (Payments)
- Package service booking rules (Booking — consumes balance)
- Gift card sale at POS (POS initiates; Memberships maintains balance)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Client CRM, Payments | Plans, clients, billing |
| Downstream | POS, Booking, Client CRM, Marketing | Redemption, balance display, campaigns |
| Partner | Payments | Subscription and wallet top-up charges |

### Core entities

`MembershipPlan`, `Membership`, `LoyaltyLedgerEntry`, `Wallet`, `PackageDefinition`, `PackageBalance`, `GiftablePack`, `RedemptionCode`

---

## 11. Retail Ecommerce

### Owns

- Online storefront configuration (per tenant/location)
- Ecommerce product presentation (merchandising layer over Inventory catalog)
- Online cart and ecommerce checkout flow
- Ecommerce subscription products
- Product recommendations engine rules
- Gift card online purchase flow
- Click-and-collect and local shipping method configuration and fulfillment status

### Does not own

- Master product catalog and stock (Inventory)
- Payment processing (Payments)
- Gift card balance ledger (Memberships)
- Order fulfillment picking in salon (may trigger POS or Inventory events)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | Identity, Inventory, Payments, Memberships | Catalog, stock, checkout, gift cards |
| Downstream | Inventory, Analytics, Integrations | Stock reservation, sales reporting, ecommerce platform sync |
| Partner | Integrations | External ecommerce connector sync |

### Core entities

`Storefront`, `EcommerceProduct`, `Cart`, `EcommerceOrder`, `ShippingMethod`, `ClickAndCollectOrder`, `RecommendationRule`

---

## 12. Analytics & BI

### Owns

- Dashboard definitions and widget configuration
- Report templates and saved reports
- Aggregated metrics and KPI snapshots
- Profitability calculation models
- Booking source attribution analysis
- Client retention cohort models
- Inventory margin reports
- Financial report layouts

### Does not own

- Source operational data (all domains — Analytics consumes events and projections)
- Raw transactional writes (read-only aggregation domain)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | All operational domains | Event streams and read replicas/projections |
| Downstream | Integrations | BI exports to external warehouses |
| Consumed by | Identity (owner/manager roles) | Permission-scoped dashboards |

### Core entities

`Dashboard`, `Report`, `MetricSnapshot`, `CohortDefinition`, `ProfitabilityModel`, `BookingSourceAttribution`

---

## 13. Integrations & Migration

### Owns

- Integration connector configurations (per tenant)
- OAuth / API credential vault for external systems
- Sync job scheduling and execution log
- Webhook endpoint registry and delivery log
- Public API keys and rate limiting configuration
- Data import jobs and migration assistant mappings
- External ID mapping tables (internal ID ↔ external system ID)

### Does not own

- Business logic of source domains (Integrations orchestrates — does not duplicate)
- Accounting journal entries (exports data — accounting system is system of record)
- Payment processing logic (Payments)

### Relationships

| Direction | Domain | Relationship |
|---|---|---|
| Upstream | All domains | Stable APIs and event contracts |
| Downstream | External systems | Accounting, calendars, Google, ecommerce, marketing, BI |
| Partner | Payments, Marketing | Provider connections may share credential patterns |

### Core entities

`IntegrationConfig`, `SyncJob`, `WebhookSubscription`, `WebhookDelivery`, `ApiKey`, `ImportJob`, `MigrationMapping`, `ExternalIdMap`

---

## Cross-Domain Interaction Rules

### Allowed

- **Synchronous API call** to a published service method in another domain (e.g., POS asks Payments to capture)
- **Domain event** published after commit (e.g., `OrderPaid`, `AppointmentCompleted`)
- **Read-model projection** for display (e.g., CRM shows order summary from POS projection)
- **Shared kernel** limited to: tenant ID, location ID, client ID, staff ID as foreign key references — not shared business logic

### Prohibited

- Direct Eloquent/query access to another domain's tables
- Duplicating entity ownership (two domains writing the same table)
- Skipping tenant scope because "it's an internal call"
- Circular domain dependencies without event-based decoupling

---

## Entity Ownership Quick Reference

| Entity | Owning domain |
|---|---|
| Tenant, User, Role | Identity |
| Client, Consent | Client CRM |
| Appointment, Resource | Booking |
| FormSubmission, PatchTest | Consultation |
| Order, Receipt | POS |
| PaymentIntent, Payout | Payments |
| Product, StockMovement | Inventory |
| Shift, CommissionEntry | Staff |
| Campaign, MessageSend | Marketing |
| Membership, LoyaltyLedger | Memberships |
| Storefront, EcommerceOrder | Retail Ecommerce |
| Report, MetricSnapshot | Analytics |
| IntegrationConfig, Webhook | Integrations |

---

*These domain boundaries are authoritative for NeatMeet OS implementation. Changes require explicit update to this document.*

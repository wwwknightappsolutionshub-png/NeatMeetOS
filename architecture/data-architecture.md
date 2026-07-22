# NeatMeet OS — Data Architecture

## Purpose

This document defines the data architecture for **NeatMeet OS**: storage choices, domain ownership, entity clusters, event flows, reporting strategy, and file handling.

---

## A. Primary Data Storage Choices

| Store | Technology | Purpose |
|---|---|---|
| **Transactional database** | PostgreSQL 16+ | System of record: all operational entities |
| **Cache** | Redis | Session, query cache, rate limits, distributed locks |
| **Queue** | Redis (Laravel Horizon) | Jobs: notifications, sync, aggregation |
| **Search (phase 1)** | PostgreSQL `tsvector` + btree indexes | Client search, product search |
| **Search (extension)** | Meilisearch | Fuzzy client/product search at scale |
| **Object / file storage** | S3-compatible | Photos, documents, signatures, receipts, exports |
| **Analytics events** | PostgreSQL `analytics_events` table | Append-only event log |
| **Analytics read models** | PostgreSQL materialized/summary tables | Dashboards, reports |
| **Analytics warehouse (future)** | ClickHouse or BigQuery via Integrations | Large-scale BI extension |

---

## B. Domain Data Ownership Principles

1. **Single writer** — Each entity has one owning domain; only that domain's services mutate it
2. **Foreign keys, not shared tables** — Other domains reference by ID (`client_id`, `appointment_id`)
3. **No cross-domain joins in controllers** — Use service interfaces or read models
4. **Events for side effects** — Downstream domains react to events, not polling foreign tables
5. **Read models for display** — CRM may show order summary from a `client_order_summaries` projection maintained by listener
6. **Tenant scoping mandatory** — Every owned table includes `tenant_id`; location-scoped tables add `location_id`

### Cross-module read patterns

| Pattern | When to use |
|---|---|
| **Service call** | Synchronous need during request (e.g., POS fetches client name) |
| **Domain event + listener** | Async side effect (e.g., Analytics records `OrderPaid`) |
| **Read model / projection** | Frequent cross-domain display (e.g., CRM timeline) |
| **API poll** | Integrations only |

---

## C. High-Level Entity Clusters

### Tenancy / org / locations / users / roles

`tenants`, `locations`, `workspaces`, `users`, `team_members`, `roles`, `permissions`, `role_permission`, `team_member_role`, `subscription_plans`, `tenant_subscriptions`, `audit_logs`, `branding_settings`

### Clients / notes / formulas / files / consent

`clients`, `client_notes`, `client_formulas`, `client_photos`, `client_documents`, `client_tags`, `client_tag_pivot`, `segments`, `consent_records`, `communication_history`

### Bookings / lines / resources / waitlist / deposits

`appointments`, `appointment_services`, `resources`, `workspaces`, `availability_rules`, `waitlist_entries`, `walk_ins`, `recurring_series`, `booking_rules`, `booking_deposit_requirements`, `deposit_payment_links` (ref → Payments)

### Orders / lines / tenders / refunds / gift cards

`orders`, `order_lines`, `tenders`, `discounts`, `refunds`, `tip_allocations`, `tax_lines`, `receipts`, `gift_card_redemptions` (ref → Memberships)

### Payments / subscriptions / payout / chargebacks

`payment_provider_configs`, `payment_intents`, `payment_captures`, `payment_links`, `deposit_transactions`, `subscriptions`, `payout_ledger_entries`, `failed_payments`, `chargebacks`

### Products / stock / movements / suppliers

`products`, `product_variants`, `stock_levels`, `stock_movements`, `suppliers`, `purchase_orders`, `stocktakes`, `reorder_rules`, `service_consumption_rules`

### Staff schedules / commission / rent / settlements

`staff_profiles`, `shifts`, `time_off_requests`, `commission_rules`, `commission_entries`, `payroll_exports`, `chair_rent_agreements`, `chair_rent_invoices`, `staff_kpis`, `staff_goals`

### Memberships / wallets / points / packages

`membership_plans`, `memberships`, `loyalty_ledger_entries`, `wallets`, `wallet_transactions`, `package_definitions`, `package_balances`, `giftable_packs`, `redemption_codes`

### Consultation / treatment

`form_templates`, `form_submissions`, `digital_signatures`, `patch_tests`, `contraindications`, `treatment_plans`, `treatment_sessions`, `aftercare_templates`, `aftercare_deliveries`, `before_after_galleries`

### Campaigns / messages / reviews

`campaigns`, `campaign_templates`, `automation_triggers`, `message_sends`, `referral_programmes`, `referral_attributions`

### Analytics / integrations

`analytics_events`, `metric_snapshots`, `report_definitions`, `dashboard_widgets`, `integration_configs`, `sync_jobs`, `webhook_subscriptions`, `webhook_deliveries`, `import_jobs`, `external_id_maps`

---

## D. Event-Driven Data Flow

### Domain event catalog (core)

| Event | Publisher | Consumers |
|---|---|---|
| `AppointmentCreated` | Booking | Marketing (confirmation), Analytics |
| `AppointmentConfirmed` | Booking | Marketing (reminder schedule), Analytics |
| `AppointmentCompleted` | Booking | POS (checkout prompt), Inventory (consumption), Staff (commission input) |
| `AppointmentCancelled` | Booking | Payments (deposit refund), Marketing |
| `DepositRequired` | Booking | Payments (create payment link) |
| `OrderCreated` | POS | Analytics |
| `OrderPaid` | POS | Payments (capture), Inventory (stock decrement), Memberships (points earn), Staff (commission), Analytics |
| `OrderRefunded` | POS | Payments, Inventory (restock), Memberships (points adjust) |
| `PaymentCaptured` | Payments | POS (confirm tender), Analytics |
| `PaymentFailed` | Payments | Marketing (dunning), Admin alerts |
| `StockBelowReorder` | Inventory | Notifications, Admin |
| `MembershipRenewed` | Memberships | Payments, Analytics |
| `PackageRedeemed` | Memberships | Booking (balance deduct), POS |
| `ClientConsentUpdated` | CRM | Marketing (eligibility) |
| `FormSubmitted` | Consultation | CRM (history), Analytics |

### Event payload standards

Every event includes:

```json
{
  "event_id": "uuid",
  "event_type": "order.paid",
  "tenant_id": "uuid",
  "location_id": "uuid|null",
  "entity_type": "order",
  "entity_id": "uuid",
  "occurred_at": "ISO8601",
  "actor": { "type": "user|system", "id": "uuid" },
  "payload": { }
}
```

### Fan-out pattern

```
Domain Service → DB transaction commit → Event dispatch
                                              ├── Sync listener (critical consistency)
                                              └── Queued listener (async: email, analytics, sync)
```

---

## E. Reporting Strategy

### Hybrid approach (chosen)

| Layer | Use |
|---|---|
| **Transactional reads** | Real-time operational screens (today's calendar, open orders) |
| **Append-only `analytics_events`** | Immutable source for all reporting |
| **Scheduled aggregation jobs** | Nightly/hourly rollups into `metric_snapshots`, `daily_revenue_summary`, etc. |
| **Read model tables** | Pre-joined data for dashboards (refreshed by listeners + cron) |
| **Warehouse export (extension)** | Integrations module exports to BigQuery/ClickHouse for heavy BI |

### Why not transactional-only for BI

POS + booking + commission reports scanning millions of rows will degrade operational DB. Event ingestion decouples reporting load.

### Report freshness tiers

| Tier | Latency | Examples |
|---|---|---|
| **Live** | Real-time | Today's bookings, open POS tabs |
| **Near-line** | 1–5 minutes | Revenue dashboard, waitlist stats |
| **Batch** | Hourly/nightly | Retention cohorts, profitability engine, payroll |

---

## F. File / Document Handling

### Storage layout

```
s3://{bucket}/{env}/{tenant_id}/{domain}/{entity_type}/{entity_id}/{filename}
```

### Document types

| Type | Domain | Access |
|---|---|---|
| Client photos | CRM | Policy-gated; pre-signed upload URL |
| Treatment photos / galleries | Consultation | Provider + manager |
| Consultation forms / signatures | Consultation | Provider + client (own) |
| Receipts PDF | POS | Client + staff |
| Payroll / export CSV | Staff / Analytics | Finance role |
| Import files | Integrations | Admin; deleted after processing |

### Metadata in database

Files stored as `files` table or domain-specific attachment tables:

`id`, `tenant_id`, `domain`, `entity_type`, `entity_id`, `storage_path`, `mime_type`, `size_bytes`, `uploaded_by`, `created_at`

### Retention

- Treatment/clinical files: tenant-configurable retention (compliance)
- Receipts: minimum 7 years recommendation (configurable)
- Import temp files: delete after 30 days

---

## Related Documents

| Document | Purpose |
|---|---|
| `domain-boundaries.md` | Domain ownership detail |
| `integration-architecture.md` | External sync data |
| `system-architecture.md` | Infrastructure topology |

---

*NeatMeet OS data architecture — Step 2 blueprint.*

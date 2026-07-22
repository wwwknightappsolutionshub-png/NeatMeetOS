# Scenario B Platform Closure

NeatMeet OS (salonOS) — **final delivery state** after Steps 3–25.

This document is the handoff reference for the completed Scenario B roadmap. No further product modules are in scope beyond what is listed here.

## What Scenario B includes

| Step | Module | Summary |
|------|--------|---------|
| 3–5 | Identity / Org | Tenancy, RBAC, Sanctum auth, admin settings |
| 6–7 | CRM (2A/2B) | Clients, tags, notes, consent, formulas, photos, documents |
| 8 | Staff (3A) | Provider profiles, availability, absences |
| 9–11 | Booking (4A–4C) | Services, appointments, waitlist, recurrence, walk-ins, day board |
| 12 | Commerce contracts | Shared checkout/deposit/allocation contracts |
| 13 | Payments (6A) | Transactions, deposits, refunds, payment links |
| 14 | Inventory (7A) | Stock catalogue, movements, suppliers, consumption |
| 15–16 | POS (8A/8B) | Checkout, appointment import, refunds, gift cards, receipts |
| 17–18 | Memberships (9A/9B) | Plans, packages, wallet, loyalty; redemption in Booking + POS |
| 19–19B | Marketing (10A/10B) | Campaigns, audiences, workflows, suppressions, simulated dispatch |
| 20 | Notifications (11A/11B) | Operational comms log, templates, preferences, admin UI |
| 21–22 | Analytics (12A/12B) | KPI dashboards, saved reports, synchronous CSV/JSON exports |
| 23–23B | Integrations (13A) | Provider accounts, delivery attempts, webhook storage, admin UI |
| 24 | Integrations (13B) | Live adapter stubs (Mailgun/Twilio/Stripe), credential validation, webhook normalization |
| 25 | Closure | Cross-module hardening, regression tests, documentation |

### Domain ownership (high level)

| Domain | Owns |
|--------|------|
| CRM | Clients, consent, enrichment assets |
| Booking | Appointments, waitlist, walk-ins, recurrence |
| Staff | Availability, bookable providers |
| Payments | Transactions, deposits, refunds |
| Inventory | Stock items, movements, suppliers |
| POS | Checkouts, tenders, gift cards, receipts |
| Memberships | Plans, subscriptions, wallet, loyalty, entitlements |
| Marketing | Campaigns, workflows, promotional messages |
| Notifications | Operational messages, preferences, templates |
| Analytics | Read-only KPIs, saved reports, export jobs |
| Integrations | Provider accounts, delivery ledger, webhook events |

### Simulation vs live providers

- **Domain dispatch** (Notifications, Marketing) drives status from `ProviderDispatchBridge` results.
- **Integrations ledger** dual-logs every outbound attempt.
- **Live drivers** (mailgun, twilio) use real HTTP APIs when credentials are valid; otherwise simulation fallback applies.
- **Webhook HMAC** validation is enforced when a provider account webhook secret is configured (`INTEGRATIONS_WEBHOOK_REQUIRE_SIGNATURE`).
- **Queue workers**: `DispatchNotificationMessageJob` / `DispatchMarketingMessageJob` / `SendMemberPushJob`; schedule includes booking reminders, upgrade campaigns, and `platform:process-billing`.

### Analytics / exports

- Metrics are computed on demand from existing domain tables (no warehouse).
- Exports run **synchronously**; files stored on local disk.
- Schedule fields on saved reports are **config only** (no cron/email delivery).

### Marketing vs Notifications

- **Marketing** — promotional campaigns, workflows, consent-gated promotional email/SMS.
- **Notifications** — operational messages (confirmations, reminders staff send manually or via triggers).
- Separate tables, permissions, admin UIs, and reporting endpoints.

## What is intentionally deferred / not built

| Area | Status |
|------|--------|
| Module 14A / 14B | Not started |
| Module 15A | Not started |
| Online client booking portal | Built — `/book/{tenantSlug}` + public `/api/v1/book/*` |
| Production Stripe/Twilio/Mailgun HTTP transport | Mailgun + Twilio HTTP live; Stripe payment-link remains stub |
| Webhook signature validation | Built (Stripe / Mailgun / Twilio HMAC) |
| Webhook → business-state reconciliation | Events stored append-only |
| Queue workers for dispatch | Built (`queue:work` + scheduled commands) |
| Platform SaaS billing / dunning | Built (`platform_invoices`, past_due → suspend) |
| Support tooling | Built (suspend, impersonate, client export/erase) |
| Member Web Push (VAPID) | Built (`minishlink/web-push` + member bootstrap key) |
| PDF/XLSX analytics exports | CSV/JSON only |
| Scheduled/emailed report delivery | Config fields only |
| Recurring membership billing automation | Manual/admin flows |
| Consultation domain | Scaffold only |
| Ecommerce domain | Scaffold only |
| Reverb/WebSocket production stack | Not wired |
| `analytics.reporting.view` / `integrations.reporting.view` | Seeded permissions, no separate routes (exports use `analytics.exports.manage`) |

## Demo / verification guide

### Credentials

```
Email: owner@demo.neatmeet.local
Password: password
```

### Recommended walkthrough

1. **Dashboard** — `/admin/dashboard` — Operations + Settings; sidebar nav across modules
2. **Online booking** — `/book/demo-salon` — public multi-step book (no login)
3. **CRM** — `/admin/clients` — open a client; check Communications tab
4. **Booking** — `/admin/bookings` — appointments, waitlist, walk-ins
5. **POS** — `/admin/pos` — create checkout, apply membership wallet/loyalty if seeded
6. **Memberships** — `/admin/memberships` — plans, client memberships, loyalty settings
7. **Marketing** — `/admin/marketing` — campaigns, workflows, message log
8. **Notifications** — `/admin/notifications` — manual send, templates, preferences
9. **Analytics** — `/admin/analytics` — overview + export a CSV from `/admin/analytics/exports`
10. **Integrations** — `/admin/integrations` — provider accounts, attempts, webhook events

### Smoke verification

```bash
cd backend && php artisan test
cd frontend && npm test && npm run build
```

## Known constraints

- Admin navigation uses a persistent sidebar (`AdminAppShell`) plus per-module secondary nav.
- Integrations overview counts use latest 200 list rows.
- Public webhook endpoint accepts unauthenticated POST; tenant binding uses `provider_account_id` when supplied (mismatched `tenant_id` rejected).
- Multi-tenant isolation enforced via `BelongsToTenant` global scope + scoped validators.

## Step 25 hardening (this closure pass)

- Webhook ingest rejects `tenant_id` / `provider_account_id` mismatches (422).
- Added `Module25PlatformClosureTest` — cross-tenant regression for waitlist, notifications templates, marketing messages/executions, appointment deposits, client memberships, integrations reporting permission boundary.
- Admin dashboard split into **Operations** and **Organization settings** cards.
- README, `local-development.md`, and domain `MODULE.md` files aligned to final state.

## Final QA snapshot

See README and CI — target:

- `php artisan test` — full backend feature suite
- `npm test` — frontend unit tests
- `npm run build` — Next.js production build

## Module documentation index

| Domain | Doc |
|--------|-----|
| Identity | `backend/app/Domains/Identity/MODULE.md` |
| CRM | `backend/app/Domains/Crm/MODULE.md` |
| Staff | `backend/app/Domains/Staff/MODULE.md` |
| Booking | `backend/app/Domains/Booking/MODULE.md` |
| Payments | `backend/app/Domains/Payments/MODULE.md` |
| Inventory | `backend/app/Domains/Inventory/MODULE.md` |
| POS | `backend/app/Domains/Pos/MODULE.md` |
| Memberships | `backend/app/Domains/Memberships/MODULE.md` |
| Marketing | `backend/app/Domains/Marketing/MODULE.md` |
| Notifications | `backend/app/Domains/Notifications/MODULE.md` |
| Analytics | `backend/app/Domains/Analytics/MODULE.md` |
| Integrations | `backend/app/Domains/Integrations/MODULE.md` |
| Consultation (deferred) | `backend/app/Domains/Consultation/MODULE.md` |
| Ecommerce (deferred) | `backend/app/Domains/Ecommerce/MODULE.md` |

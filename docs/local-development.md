# Local Development — NeatMeet OS

## Prerequisites

- PHP 8.3+, Composer 2
- Node.js 22+, npm
- SQLite (default local) or PostgreSQL 16 (Docker)
- Redis (optional locally; required for Docker queue)

## Quick start (native — Windows / macOS / Linux)

### 1. Backend

```bash
cd backend
cp .env.example .env   # Windows: copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed    # local only — demo tenant + owner user
php artisan serve
```

API: `http://localhost:8000`  
Health: `http://localhost:8000/api/v1/health`  
Laravel probe: `http://localhost:8000/up`

**Demo login:** `owner@demo.neatmeet.local` / `password` — then open `/admin/dashboard` or `/admin/settings/account`.

### Module 1 admin routes

| Route | Purpose |
|---|---|
| `/admin/settings/account` | Organization profile |
| `/admin/settings/branding` | Brand colors, logo URL, support contact |
| `/admin/settings/locations` | Location CRUD + **salon opening hours** |
| `/admin/staff` | Staff list with add / edit / activate + link to availability |
| `/admin/staff/[id]?tab=availability` | Weekly staff availability windows |
| `/admin/settings/workspaces` | Chair/room/station/slot CRUD |
| `/admin/settings/team` | Team member admin |
| `/admin/settings/access` | Roles, permissions, team assignments |
| `/admin/settings/subscription` | Current plan, status, limits |
| `/admin/settings/audit` | Audit log (requires `identity.audit.view`) |

### Seeded permissions (owner role)

| Permission | Purpose |
|---|---|
| `identity.view` | Read admin settings |
| `identity.manage` | Org, branding, locations, workspaces, team |
| `identity.access.manage` | Role CRUD and permission assignment |
| `identity.audit.view` | Audit log visibility |
| `booking.view` | Booking calendar, appointments, waitlist, walk-ins |

Demo subscription: **Starter** plan on **trial** (14 days). Manager role seeded with limited permissions.

### Module 2A — CRM routes

| Route | Purpose |
|---|---|
| `/admin/clients` | Client list, search, create |
| `/admin/clients/[id]` | Profile, tags, notes, consent, timeline |

### Module 2B — CRM enrichment

| Route / tab | Purpose |
|---|---|
| `/admin/clients/[id]` → Formulas | Colour/treatment formula records |
| `/admin/clients/[id]` → Photos | Photo asset references (storage path/URL) |
| `/admin/clients/[id]` → Documents | Document asset references |
| Profile → CRM details | Preferred stylist, preferences, loyalty display placeholder |

Owner role includes `crm.view` + `crm.manage`. Demo seed includes 2 clients, VIP/Regular tags, notes, consent history, plus Alex Taylor formula/photo/document records.

**Asset storage:** photos and documents use `storage_path` string references (same pattern as tenant branding). Binary upload pipeline is deferred.

### Client referral programme

| Surface | Purpose |
|---|---|
| `/member/{tenantSlug}` → **Refer** tab | Share join link, WhatsApp message URL, typed email invites (max 20) |
| `/join/{tenantSlug}?ref=CODE` | Public join attributes new clients to the referrer |
| `GET /api/v1/member/referral` | Share payload + stats |
| `POST /api/v1/member/referral/email-invites` | Send invite emails via notification dispatch |

Rewards: referrer **+100** loyalty on successful new join; referred **+300** on first membership plan or package purchase (once).

### Module 3A — Staff operations

| Route | Purpose |
|---|---|
| `/admin/staff` | Provider list (employment type, bookable status) |
| `/admin/staff/[id]` | Profile, availability, absences, operating scope |

Permissions: `staff.view`, `staff.manage`. Demo seed includes bookable owner + stylist with availability windows and a holiday absence.

### Module 4A — Booking

| Route | Purpose |
|---|---|
| `/admin/bookings` | Day board, filters, quick actions, create |
| `/admin/bookings/[id]` | Detail, reschedule, status, cancel, rebook, workspace |
| `/admin/bookings/services` | Bookable service catalogue |
| `/admin/bookings/walk-ins` | Walk-in queue register and seat |
| `/admin/bookings/waitlist` | Waitlist with filters and fulfilment |

### Online booking portal

| Route / API | Purpose |
|---|---|
| `/book/{tenantSlug}` | Public multi-step booking (demo: `/book/demo-salon`) |
| `GET /api/v1/book/catalog` | Online services, locations, providers (`X-Tenant-Slug`) |
| `GET /api/v1/book/slots` | Available slots for service/location/date |
| `POST /api/v1/book/appointments` | Create appointment (`booking_source=online`) |

No staff login required. Admin sidebar **Book online** opens the portal for the signed-in tenant slug.

Permissions: `booking.view`, `booking.manage`. Demo seed includes Cut & Blow Dry, Full Colour, sample appointments, walk-in queue entry, no-show example, and contacted waitlist entry.

### Module 4B — Booking expansion

| Route | Purpose |
|---|---|
| `/admin/bookings` | Recurring series option on create |
| `/admin/bookings/waitlist` | Waitlist CRUD and fulfilment |
| Services / appointment detail | Deposit requirement metadata (booking-side only) |

**Recurrence behavior:** valid occurrences are created; conflicts are skipped and reported (series is not rolled back).

**Deposit split:** Booking stores requirement + snapshot; Payments records collection via `commerce_deposit_records`.

### Module 4C — Front desk operations

| Route | Purpose |
|---|---|
| `/admin/bookings` | Day board with workspace occupancy, check-in / no-show / cancel quick actions |
| `/admin/bookings/walk-ins` | Register walk-in, seat when provider/workspace available |
| `/admin/bookings/[id]` | Rebook, workspace reassignment, no-show reason, status correction |
| `/admin/bookings/waitlist` | Filter by status/location/provider/service; inline fulfilment |

**Walk-in model:** appointments with `booking_source=walk_in` and `walk_in_stage`; waiting entries do not block the schedule until seated.

**Lifecycle:** centralized transition rules; `no_show` is distinct from `cancelled`; terminal states corrected via `correct-status` with a note.

### Step 12 — Commerce Contract Sprint

Contract docs: `architecture/commerce/`  
Code foundation: `backend/app/Shared/Commerce/`

| API (contract inspection) | Purpose |
|---|---|
| `GET /api/v1/admin/appointments/{id}/checkout-import` | Maps appointment → checkout import DTO (POS input contract) |

Skeleton tables: `commerce_checkouts`, `commerce_checkout_lines`, `commerce_checkout_appointments`, `commerce_deposit_records`, `commerce_events`.  
Appointments gain `billing_settlement_status` (POS authority on settlement).

Re-seed after schema changes: `php artisan migrate:fresh --seed` (local only).

### Step 13 — Module 6A Payments foundation

| Route | Purpose |
|---|---|
| `/admin/payments` | Transaction list, filters, summary cards |
| `/admin/payments/[paymentId]` | Detail, allocations, refunds, status actions |
| `/admin/payments/failed` | Failed payment list |
| `/admin/bookings/[id]` | Deposit pay / waive / refund (Payments integration) |

| API | Purpose |
|---|---|
| `GET/POST /api/v1/admin/payments/*` | Transactions, manual payments, payment links |
| `GET/POST /api/v1/admin/appointments/{id}/deposit/*` | Deposit inspect, pay, waive, refund |
| `GET /api/v1/admin/payments/summary` | Operational totals |

Permissions: `payments.view`, `payments.manage`, `payments.refund`, `payments.reporting.view`. Owner has full set; manager has view + reporting.

**Demo deposit flow:** sign in as demo owner → open appointment with pending deposit or browse `/admin/payments` for seeded succeeded deposit (`NM-DEMO0002`), pending payment link, failed attempt, and refund example.

### Step 14 — Module 7A Inventory foundation

| Route | Purpose |
|---|---|
| `/admin/inventory` | Item catalogue, filters, create |
| `/admin/inventory/items/[itemId]` | Levels, movements, consumption rules |
| `/admin/inventory/suppliers` | Supplier CRUD |
| `/admin/inventory/low-stock` | Below reorder point |
| `/admin/inventory/movements` | Movement ledger |

| API | Purpose |
|---|---|
| `GET/POST /api/v1/admin/inventory/items` | Item catalogue |
| `GET/PUT /api/v1/admin/inventory/items/{id}/levels/{locationId}` | Reorder thresholds / opening stock |
| `POST /api/v1/admin/inventory/movements` | Manual adjustments, restock, waste |
| `POST /api/v1/admin/inventory/consume` | Commerce consumption contract bridge |

Permissions: `inventory.view`, `inventory.manage`, `inventory.adjust`, `inventory.reporting.view`.

**Demo stock flow:** browse `/admin/inventory` for seeded retail/professional items → open **Developer 20vol** (low stock) → record restock movement.

### Module 8A — POS & checkout

| Route | Purpose |
|-------|---------|
| `/admin/pos` | Checkout list, filters, open new checkout |
| `/admin/pos/[checkoutId]` | Live basket, import, deposit credit, payments, complete |

| API | Purpose |
|-----|---------|
| `GET/POST /api/v1/admin/pos/checkouts` | List/create checkouts |
| `POST /api/v1/admin/pos/checkouts/{id}/import-appointment` | Import eligible appointment lines |
| `POST /api/v1/admin/pos/checkouts/{id}/apply-deposit-credit` | Apply collected deposit |
| `POST /api/v1/admin/pos/checkouts/{id}/payments` | Record split tenders |
| `POST /api/v1/admin/pos/checkouts/{id}/complete` | Settle appointments + consume stock |

Permissions: `pos.view`, `pos.manage`, `pos.checkout.complete`.

**Demo POS flow:** sign in as demo owner → `/admin/pos` → open draft checkout → import appointment `NM-POS0001` → apply deposit credit → record payment → complete. Seeded completed examples: mixed sale (B), retail-only (C), split tender (D).

### Module 8B — POS advanced commerce

| Route | Purpose |
|-------|---------|
| `/admin/pos/[checkoutId]` (completed) | Refunds, retail returns, reopen, receipt resend |
| `/admin/pos/[checkoutId]` (open) | Gift card sale lines, gift card redemption |

| API | Purpose |
|-----|---------|
| `GET/POST /api/v1/admin/pos/checkouts/{id}/refunds` | Refund history / create refund |
| `POST /api/v1/admin/pos/checkouts/{id}/returns` | Retail line return + stock reversal |
| `POST /api/v1/admin/pos/checkouts/{id}/reopen` | Reopen completed checkout (no refunds) |
| `POST /api/v1/admin/pos/checkouts/{id}/lines/gift-card` | Add gift card sale line |
| `POST /api/v1/admin/pos/checkouts/{id}/apply-gift-card` | Apply gift card credit |
| `GET /api/v1/admin/pos/checkouts/{id}/receipts` | Receipt history |
| `POST /api/v1/admin/pos/checkouts/{id}/receipts/resend` | Resend receipt (email/print placeholder) |

Permissions: `pos.refund`, `pos.checkout.reopen`, `pos.receipt.manage` (plus 8A permissions).

**Demo 8B flow:** open completed checkout C (retail-only) → partial refund or return 1 unit → resend receipt by email. For gift cards: add gift card line on open checkout → complete → redeem code on a new checkout.

See `backend/app/Domains/Pos/MODULE.md` for ownership split and deferred items.

### Module 9A — Memberships, wallet, loyalty & packages

| Route | Purpose |
|-------|---------|
| `/admin/memberships` | Summary cards (MRR, wallet liability, packages) |
| `/admin/memberships/plans` | Membership plan catalogue |
| `/admin/memberships/packages` | Prepaid package products |
| `/admin/memberships/subscriptions` | Client membership assignments |
| `/admin/memberships/wallet` | Wallet ledger + manual adjustments |
| `/admin/memberships/loyalty` | Loyalty points ledger |
| `/admin/memberships/client-packages` | Client package balances, redeem/restore |

| API | Purpose |
|-----|---------|
| `GET /api/v1/admin/memberships/summary` | Operational summary |
| `GET/POST /api/v1/admin/memberships/plans` | Plan catalogue |
| `GET/POST /api/v1/admin/memberships/packages` | Package products |
| `GET/POST /api/v1/admin/memberships/client-memberships` | Subscriptions |
| `GET/POST /api/v1/admin/memberships/wallet-entries` | Wallet ledger |
| `GET/POST /api/v1/admin/memberships/loyalty-entries` | Loyalty ledger |
| `GET/POST /api/v1/admin/memberships/client-packages` | Package balances |

Permissions: `memberships.view`, `memberships.manage`, `memberships.reporting.view`.

**Demo flow:** sign in as demo owner → `/admin/memberships` → review summary → open Alex Taylor's Blow Dry Club subscription → wallet shows membership credit + manual adjustment → client package shows 5/6 remaining after demo redemption.

**Deferred:** Stripe recurring billing, auto-renewal jobs, public purchase portal, automatic POS earning/redemption.

See `backend/app/Domains/Memberships/MODULE.md`.

### Module 9B — Membership redemption (Booking + POS)

| Route | Purpose |
|-------|---------|
| `/admin/memberships/loyalty-settings` | Tenant loyalty redemption rule (e.g. 100 pts = £10) |
| `/admin/bookings/[appointmentId]` | Package reserve/release per service line |
| `/admin/pos/[checkoutId]` | Membership benefits panel (wallet, loyalty, packages) |

**Demo flow after seed:**

1. `/admin/memberships/loyalty-settings` — confirm 100 points = £10 enabled
2. `/admin/bookings/{appointmentId}` — Alex Taylor appointment with reserved blow-dry package on service line
3. `/admin/pos/{checkoutId}` — apply wallet / loyalty / import appointment with reserved package
4. Complete checkout to see redeemed package; void/reopen restores balances

**Deferred:** automatic loyalty earn rules, partial proportional refund restoration, public online redemption.

### Module 10A — Marketing automation

| Route | Purpose |
|-------|---------|
| `/admin/marketing` | Overview dashboard, recent runs, sent/failed counts |
| `/admin/marketing/templates` | Message templates, placeholders, preview render |
| `/admin/marketing/audiences` | Saved segments, rule builder, recipient preview |
| `/admin/marketing/campaigns` | Broadcast + automation campaigns, status lifecycle |
| `/admin/marketing/runs` | Generate reminders/rebook/review/win-back, dispatch runs, inspect messages |
| `/admin/marketing/settings` | Automation defaults (reminder hours, win-back threshold, etc.) |

**Permissions**

| Permission | Purpose |
|------------|---------|
| `marketing.view` | Read templates, audiences, campaigns, runs |
| `marketing.manage` | Create/update templates, audiences, campaigns, settings |
| `marketing.dispatch` | Generate automation runs and simulated dispatch |
| `marketing.reporting.view` | Marketing summary and run/campaign reporting |

Owner: all four. Manager: `marketing.view` + `marketing.reporting.view` only.

**Provider transport:** Module 10A uses Integrations provider dispatch for email/SMS/WhatsApp (live or simulation fallback). **Push** and **in-app** are native CRM transports (Web Push + `client_notices` member inbox).

**Demo flow after seed:**

1. `/admin/marketing` — dashboard with campaign/run totals
2. `/admin/marketing/templates` — preview "Booking Reminder — Email" with sample variables
3. `/admin/marketing/runs` — inspect reminder run (sent + skipped), dispatch pending review-request messages
4. `/admin/marketing/settings` — confirm 24h reminder, 90-day win-back defaults

**Scheduler:** `php artisan schedule:work` (or cron) runs `marketing:run-scheduled` every 5 minutes — win-back (inactive, 14d), birthday, membership reminder (last week of month), monthly book nudge (week 1), pending dispatch. Welcome email queues ~15s after client create; welcome in-app once on first member login. Email HTML uses branded chrome (Anek + tenant colour + Powered by NeatMeet OS).

**Still deferred:** unsubscribe center, visual email builder, live provider webhooks.

### Module 10B — Marketing automation & delivery operations

| Route | Purpose |
|-------|---------|
| `/admin/marketing/workflows` | Workflow CRUD, steps, activate/pause/archive, test-run |
| `/admin/marketing/workflows/[workflowId]` | Workflow detail, step editor, test execution |
| `/admin/marketing/executions` | Execution list, process queued, run birthday automations |
| `/admin/marketing/executions/[executionId]` | Step history, message outcomes, cancel |
| `/admin/marketing/messages` | Operational message inspection |
| `/admin/marketing/messages/[messageId]` | Delivery state, attempts, mark delivered/opened/clicked/failed/unsubscribe |
| `/admin/marketing/suppressions` | Suppression list, manual add, lift/deactivate/reactivate |

**API additions** (`/api/v1/admin/marketing/...`):

| Endpoint group | Permission |
|----------------|------------|
| `GET/POST/PUT workflows`, `PATCH status`, `PUT steps` (bulk sync), `POST/PUT steps`, `PUT steps/reorder`, `PATCH steps/{id}/archive` | `marketing.view` / `marketing.manage` |
| `GET workflows/{id}/executions` | `marketing.view` |
| `POST workflows/{id}/run-test` | `marketing.dispatch` |
| `GET executions`, `PATCH cancel`, `POST process`, `POST automations/run-birthday` | `marketing.view` / `marketing.dispatch` |
| `GET messages`, operational `POST mark-*` / `unsubscribe` | `marketing.view` / `marketing.dispatch` |
| `GET/POST suppressions`, `PATCH lift` / `deactivate` / `reactivate` | `marketing.view` / `marketing.manage` |
| `GET reporting/automations/*` and `GET reports/automation/*` (aliases) | `marketing.reporting.view` |

**Domain trigger integrations (synchronous, non-blocking):**

- CRM `ClientService::create` → `client_created`
- CRM `ClientConsentService::record` → `consent_granted` / `consent_withdrawn`
- Booking `AppointmentLifecycleService::transition` → `appointment_completed` / `appointment_no_show`
- Memberships `ClientMembershipService::assign` → `membership_started`
- Memberships `ClientMembershipService::cancel` → `membership_cancelled` (immediate only)
- Birthday: admin-only via `POST /marketing/automations/run-birthday`

**Demo flow (10B, after seed):**

1. `/admin/marketing/workflows` — "Birthday Email", "No-show Follow-up", "New Client Welcome" (all active)
2. `/admin/marketing/executions` — completed, queued, and skipped executions
3. `/admin/marketing/messages` — delivered, failed, and sent messages
4. `/admin/marketing/suppressions` — Alex Taylor unsubscribed (email)
5. `/admin/marketing/workflows/[id]` — test-run against a demo client

**Deferred beyond 10B:** background queue workers, `tag_client` / `internal_note` step execution, public preference centre, real provider webhooks, drip/branching visual builder.

### Module 11A — Notifications & communications foundation (backend only)

**Domain:** `backend/app/Domains/Notifications/`

**API** (`/api/v1/admin/notifications/...`):

| Route | Permission |
|-------|------------|
| `GET/POST/PUT templates`, `POST templates/install-samples`, `PATCH templates/{id}/archive` | `notifications.view` / `notifications.manage` |
| `GET messages`, `GET messages/{id}`, `POST messages/manual`, `POST messages/{id}/cancel`, `POST messages/{id}/mark-delivered` | `notifications.view` / `notifications.manage` |
| `GET/PUT preferences/{clientId}`, `POST preferences/{clientId}/sync-from-consent` | `notifications.view` / `notifications.manage` |
| `GET/PUT settings` | `notifications.view` / `notifications.manage` |
| `GET timeline/clients/{clientId}` | `notifications.view` |
| `GET reporting/summary`, `reporting/failures`, `reporting/by-purpose` | `notifications.reporting.view` |

**Permissions:**

| Permission | Scope |
|------------|-------|
| `notifications.view` | Read templates, messages, preferences, settings, timeline |
| `notifications.manage` | Create/update templates, send/cancel messages, update preferences/settings |
| `notifications.reporting.view` | Operational reporting summary, failures, by-purpose |

Owner: all three. Manager: `notifications.view` + `notifications.reporting.view` only.

**Ownership split vs Marketing:** Marketing owns campaigns/workflows/suppressions for promotional and journey messaging. Notifications owns the operational communication log (booking confirmations, payment links, membership notices, manual staff messages). Separate template and message tables; CRM consent remains authoritative — `notification_preferences` is an operational projection.

**Demo flow (11A, after seed):**

1. `GET /notifications/templates` — booking reminder email, payment link SMS, membership expiry email
2. `GET /notifications/messages` — sent booking reminder (Alex), payment link (Alex), failed confirmation, suppressed reminder (Jordan)
3. `GET /notifications/preferences/{clientId}` — consent-projected channel flags
4. `GET /notifications/settings` — 24h reminder / 3-day payment reminder defaults
5. `GET /notifications/timeline/clients/{clientId}` — normalised communication history
6. `GET /notifications/reporting/summary` — sent/failed/suppressed counts

**Admin UI (Step 20B, frontend):**

| Route | Purpose |
|-------|---------|
| `/admin/notifications` | Overview — summary cards (sent/failed/suppressed/queued), recent failures, by-purpose breakdown, quick links |
| `/admin/notifications/messages` | Operational communication log with channel/status/source/purpose/date filters |
| `/admin/notifications/messages/[messageId]` | Message detail — metadata, body preview, attempts, cancel / mark-delivered, failure & suppression reasons |
| `/admin/notifications/messages/new` | Manual operational message (client search, channel, purpose, optional template, send) |
| `/admin/notifications/templates` | Template list with channel/category filters (incl. **In-app**), Install sample templates, inline create |
| `/admin/notifications/templates/[templateId]` | Template edit (system templates read-only) |
| `/admin/notifications/preferences` | Client lookup → operational preference editor + "Sync from CRM consent" |
| `/admin/notifications/settings` | Tenant operational notification defaults |

The client detail page (`/admin/clients/[clientId]`) gains a **Communications** tab rendering the notification timeline. Notifications is a distinct nav entry from Marketing; the UI labels all delivery as simulated.

**Deferred beyond 11A:** real provider integrations, cron/scheduler for reminders, public preference centre, automatic Booking/Payments lifecycle hooks (callable `NotificationTriggerService` is ready).

### Step 21A — Analytics & reporting foundation (Module 12A, backend only)

Read-only cross-domain operational analytics. No frontend pages in this step.

**API** (`/api/v1/admin/analytics/...`):

| Endpoint | Permission | Query params |
|----------|------------|--------------|
| `GET overview` | `analytics.view` | `from`, `to`, `location_id`, `provider_id` |
| `GET bookings` | `analytics.view` | `from`, `to`, `location_id`, `provider_id` |
| `GET revenue` | `analytics.view` | `from`, `to`, `location_id`, `provider_id` |
| `GET clients` | `analytics.view` | `from`, `to`, `location_id` |
| `GET inventory` | `analytics.view` | `from`, `to`, `location_id` |
| `GET communications` | `analytics.view` | `from`, `to` |

| Permission | Purpose |
|------------|---------|
| `analytics.view` | Read all analytics endpoints |
| `analytics.reporting.view` | Reserved for future granular reporting (seeded; Manager receives both) |

Owner: both permissions. Manager: `analytics.view` + `analytics.reporting.view`.

Default date window: last 30 days when `from`/`to` omitted.

**Local verification (owner token):**

1. `GET /analytics/overview` — bookings, payments, POS, clients, memberships, inventory, marketing, notifications sections
2. `GET /analytics/bookings` — status summary, daily series, provider/service breakdown
3. `GET /analytics/revenue` — payment + deposit + POS totals, status/type/provider breakdowns
4. `GET /analytics/clients` — growth, consent uptake, membership attachment
5. `GET /analytics/inventory` — low stock list, movement breakdown
6. `GET /analytics/communications` — marketing + notification delivery counts

See `backend/app/Domains/Analytics/MODULE.md` for timestamp semantics and deferred scope.

**Admin UI (Step 21B, frontend):**

| Route | Purpose |
|-------|---------|
| `/admin/analytics` | Operational overview — KPI stat cards + per-domain section cards linking to subpages |
| `/admin/analytics/bookings` | Booking status cards, daily activity, provider performance, top services, cancellation/no-show rates |
| `/admin/analytics/revenue` | Payments/deposits/POS cards, daily revenue, status/type/provider breakdowns |
| `/admin/analytics/clients` | Client totals, growth series, consent uptake, tags, membership attachment |
| `/admin/analytics/inventory` | Low-stock snapshot, movement breakdown, top consumed items |
| `/admin/analytics/communications` | Marketing vs Notifications delivery counts + by-channel tables (visually separated) |

Filters: date range everywhere; location on overview/bookings/revenue/clients/inventory; provider on overview/bookings/revenue. Blank dates fall back to the backend's last-30-days window. No chart library — the UI uses stat cards, tables, and daily-series lists. Frontend types/service live in `frontend/lib/analytics-types.ts` and `frontend/services/analytics.service.ts`; reusable components under `frontend/components/admin/analytics/`.

### Step 22A — Analytics exports & saved reports (Module 12B, backend only)

Saved report presets + synchronous CSV/JSON export jobs on top of the 12A datasets. All routes require `analytics.exports.manage` (seeded to Owner + Manager).

**API** (`/api/v1/admin/analytics/...`):

| Route | Description |
|-------|-------------|
| `GET/POST saved-reports` | List / create saved report presets (`report_type`, filters JSON, `export_format`, scheduling fields) |
| `GET/PUT saved-reports/{id}` | Show / update a preset |
| `PATCH saved-reports/{id}/archive` | Archive a preset (archived presets cannot be run) |
| `POST saved-reports/{id}/run` | Create + run an export from a preset |
| `GET/POST exports` | List / create + run an ad-hoc export (`report_type`, `status` filters) |
| `GET exports/{id}` | Show an export job |
| `GET exports/{id}/download` | Download the generated file (completed jobs only) |

**Behaviour:**

- Exports execute **synchronously** in-request (no queues/workers). Files persist to the `local` disk under `analytics/exports/{tenantId}/analytics-{type}-{Y-m-d-His}.{ext}`.
- Formats: `csv` (compact primary rows per report type) and `json` (full payload wrapped with `report_type` / `generated_at` / `filters` / `range` / `data`).
- Scheduling fields (`is_scheduled`, `schedule_frequency`, etc.) are stored as **config only** — no cron/emailed delivery yet.

**Local check:** log in as owner, `POST /analytics/exports { "report_type": "overview", "export_format": "json" }`, then `GET /analytics/exports/{id}/download`.

See `backend/app/Domains/Analytics/MODULE.md` (Module 12B section) for full detail.

**Deferred beyond 12B:** PDF/XLSX exports, queued/background exports, emailed + cron-scheduled report delivery, custom report builder, chart image generation, BI connectors, warehouse/snapshots.

**Admin UI (Step 22B, frontend):**

| Route | Purpose |
|-------|---------|
| `/admin/analytics/reports` | Saved reports list — create preset, edit, archive, run export now |
| `/admin/analytics/reports/[reportId]` | Saved report detail/edit — update filters/format/schedule config, run export |
| `/admin/analytics/exports` | Ad-hoc export panel + export job history with status and download |

Navigation: Analytics shell adds **Reports** and **Exports** links alongside the Step 21B overview subpages. Filter fields in create/edit forms respect report-type capability (communications: no location/provider; clients/inventory: no provider). Scheduling fields are editable but config-only — no background delivery yet. Downloads use authenticated blob fetch via `downloadAnalyticsExport()` (not bare `download_url` links). Frontend types/service extended in `frontend/lib/analytics-types.ts` and `frontend/services/analytics.service.ts`; components under `frontend/components/admin/analytics/`.

### Step 23A — Provider integrations foundation (Module 13A, backend only)

**Migration:** `2026_07_08_410000_create_integrations_module_13a_tables.php`

**Tables:** `provider_accounts`, `provider_delivery_attempts`, `provider_webhook_events`

**Permissions:** `integrations.view`, `integrations.manage`, `integrations.reporting.view` (Owner: all; Manager: view + reporting)

**Admin API:** `/api/v1/admin/integrations/provider-accounts`, `/provider-attempts`, `/provider-events`

**Public webhook intake:** `POST /api/v1/integrations/webhooks/{driver}` (HMAC verified when account webhook secret is set; rate-limited)

**Deferred beyond 13A:** automatic business-state reconciliation from webhooks.

**Simulation-first:** Notifications / Marketing / Payments continue existing simulation dispatch; each send also creates a `provider_delivery_attempts` row. When no active default account exists, implicit simulation fallback applies (`provider_account_id` null).

**Local check:** create a provider account, send a manual notification, then `GET /integrations/provider-attempts?source_domain=notifications`.

See `backend/app/Domains/Integrations/MODULE.md` for full detail.

**Admin UI (Step 23B, frontend):**

| Route | Purpose |
|-------|---------|
| `/admin/integrations` | Overview — account/attempt/event summary, recent failures, quick links |
| `/admin/integrations/provider-accounts` | Provider account list, filters, inline create, test/archive/set-default |
| `/admin/integrations/provider-accounts/[accountId]` | Account detail/edit, activate/deactivate, test |
| `/admin/integrations/provider-attempts` | Delivery attempt ledger with source/status/date filters |
| `/admin/integrations/provider-attempts/[attemptId]` | Read-only attempt detail + simulation retry |
| `/admin/integrations/provider-events` | Webhook event list |
| `/admin/integrations/provider-events/[eventId]` | Read-only event payload viewer |

Navigation: Dashboard adds **Integrations** link. Types in `frontend/lib/integrations-types.ts`; API client in `frontend/services/integrations.service.ts`; shell/components under `frontend/components/admin/integrations/`. Credentials are never shown raw (`has_credentials` + `config_summary` only). Overview aggregates are computed client-side from list endpoints.

### Step 24 — Live provider adapters (Module 13B)

**Adapter layer:** `MailgunEmailAdapter`, `TwilioSmsAdapter`, `StripePaymentLinkAdapter` (stub transport — no production SDK). Routed via `ProviderAdapterRegistry` from `ProviderDispatchService`.

**Credentials:** encrypted JSON on provider accounts; test endpoint validates shape (`credentials_valid_stub` / `credentials_missing`). Category/driver combos validated (e.g. Stripe only on `payment_gateway`).

**Fallback:** missing/invalid credentials on a live default account → simulation fallback with `live_fallback_reason` metadata.

**Webhooks:** driver-specific payload normalization (Stripe/Mailgun/Twilio) stored in `metadata.normalized`; signature validation deferred.

**Tests:** `tests/Feature/Module13BIntegrationsLiveAdaptersTest.php`

**Deferred beyond 13B:** automatic reconciliation, live Stripe payment-link retry (Mailgun/Twilio HTTP live).

**Admin UI:** credential form fields per live driver, missing-credential warnings, live vs simulation transport badges on attempts.

### Step 25 — Final hardening / closure

Cross-module regression tests (`tests/Feature/Module25PlatformClosureTest.php`), webhook tenant/account binding hardening, admin dashboard Operations vs Settings split, and [docs/final-platform-closure.md](final-platform-closure.md).

### Recommended demo walkthrough

1. Sign in as `owner@demo.neatmeet.local` / `password`
2. Open `/admin/dashboard` → explore Operations links
3. CRM client → Communications tab; Booking → appointment; POS → checkout
4. Memberships → plan + wallet; Marketing → campaign/workflow; Notifications → manual send
5. Analytics → overview + CSV export; Integrations → provider account + attempt ledger

### 2. Frontend

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

Web: `http://localhost:3000` → redirects to `/health`

### 3. Queue worker + scheduler (production / async local)

```bash
cd backend
# Process jobs (DispatchNotificationMessageJob, marketing, member push)
php artisan queue:work

# Run scheduled tasks (booking reminders, upgrade campaigns, platform billing)
php artisan schedule:work
# Production cron alternative:
# * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Horizon is optional and not packaged by default; `queue:work` is the supported worker.

## Docker (PostgreSQL + Redis + MinIO + Mailpit)

```bash
cd docker
docker compose up --build
```

Run migrations inside backend container:

```bash
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
```

## Tests

```bash
cd backend && php artisan test
cd frontend && npm test && npm run build
```

## Lint / format

```bash
cd backend && vendor/bin/pint
cd frontend && npm run lint
```

## Environment variables

| Variable | App | Purpose |
|---|---|---|
| `APP_URL` | backend | API base URL |
| `FRONTEND_URL` | backend | Sanctum stateful domains |
| `DB_*` | backend | Database connection |
| `REDIS_*` | backend | Cache / queue |
| `NEXT_PUBLIC_API_URL` | frontend | API client base (`/api/v1`) |
| `NEXT_PUBLIC_TENANT_SLUG` | frontend | Default tenant header |

## Further reading

- [final-platform-closure.md](final-platform-closure.md) — Scenario B delivery scope, deferred items, demo guide
- Domain `MODULE.md` files under `backend/app/Domains/*/MODULE.md`

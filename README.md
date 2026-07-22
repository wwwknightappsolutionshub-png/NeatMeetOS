# NeatMeet OS

Production-grade, multi-tenant **salon operating system** — Laravel modular monolith API + Next.js PWA.

## Repository

| Path | Description |
|---|---|
| `backend/` | Laravel 13 API (`/api/v1`) |
| `frontend/` | Next.js 16 web app (admin, desk, provider shells) |
| `product/` | Product vision, scope lock, delivery roadmap |
| `architecture/` | Technical architecture and repo structure |
| `docs/` | Developer guides |

## Scenario B platform status (complete)

Steps 3–25 deliver a multi-tenant salon operating system: CRM, booking, staff, payments, inventory, POS, memberships, marketing, notifications, analytics, and provider integrations. **Scenario B ends here** — see [docs/final-platform-closure.md](docs/final-platform-closure.md) for the full handoff reference.

**Step 24 (Module 13B):** Live provider adapter foundation — Mailgun/Twilio/Stripe stub adapters, credential validation, simulation fallback, driver-specific webhook normalization (`/admin/integrations`).

**Step 25:** Final hardening / closure — cross-module regression tests, webhook tenant binding hardening, dashboard nav polish, documentation closure.

### Delivered modules (summary)

| Step | Area |
|------|------|
| 4–5 | Organization settings |
| 6–7 | CRM |
| 8 | Staff |
| 9–11 | Booking |
| 13 | Payments |
| 14 | Inventory |
| 15–16 | POS |
| 17–18 | Memberships + redemption |
| 19–19B | Marketing automation |
| 20 | Notifications |
| 21–22 | Analytics + exports |
| 23–24 | Integrations foundation + live adapter stubs |

### Deferred beyond Scenario B

Online client booking, production SDK transport (Stripe/Twilio/Mailgun HTTP), webhook signature validation, automatic webhook reconciliation, queue workers, PDF/XLSX exports, scheduled/emailed analytics delivery, recurring membership billing automation, Modules 14A/14B/15A. See [docs/final-platform-closure.md](docs/final-platform-closure.md).

## Implementation history (Steps 3–23B detail)

- Domain folder scaffold for all 13 bounded contexts
- Identity / tenancy skeleton (tenants, locations, workspaces, team members, RBAC tables)
- Sanctum auth + health/version/shell API endpoints
- Next.js workspace shells + `/health` stack proof page
- CI: backend tests + Pint; frontend test + build
- Docker Compose for local PostgreSQL, Redis, MinIO, Mailpit

**Step 4–5 (Module 1):** Organization settings — account, branding, locations, workspaces, team, access, subscription, audit (`/admin/settings/*`).

**Step 6 (Module 2A):** Client CRM — profiles, tags, notes, consent, timeline (`/admin/clients`).

**Step 7 (Module 2B):** CRM enrichment — formulas, photos, documents, profile enrichment (`/admin/clients/[id]` tabs).

**Step 8 (Module 3A):** Staff operations — provider profiles, availability, absences, operating scope (`/admin/staff`).

**Step 9 (Module 4A):** Booking foundation — services, appointments, scheduling validation (`/admin/bookings`).

**Step 10 (Module 4B):** Booking expansion — recurring series, waitlist, deposit contracts (`/admin/bookings/waitlist`).

**Step 11 (Module 4C):** Front desk — walk-ins, day board, rebook, lifecycle corrections.

**Step 12:** Commerce contract sprint — shared commerce foundation, checkout-import contract.

**Step 13 (Module 6A):** Payments foundation — transactions, deposit collection, refunds, admin UI (`/admin/payments`).

**Step 14 (Module 7A):** Inventory foundation — stock catalogue, movements, suppliers, consumption rules (`/admin/inventory`).

**Step 15 (Module 8A):** POS & checkout foundation — basket, appointment import, deposit credit, split tenders, completion (`/admin/pos`).

**Step 16 (Module 8B):** POS advanced commerce — refunds, retail returns, reopen, gift cards, receipts, discount governance (`/admin/pos`).

**Step 17 (Module 9A):** Memberships foundation — plans, packages, subscriptions, wallet, loyalty, entitlements (`/admin/memberships`).

**Step 18 (Module 9B):** Membership redemption wired into Booking + POS — package reserve/redeem/restore, wallet credit, loyalty redemption, void/reopen/refund restoration (`/admin/pos`, `/admin/bookings`, `/admin/memberships/loyalty-settings`).

**Step 19 (Module 10A):** Marketing automation foundation — consent-aware campaigns, audiences, templates, admin-triggered automations (reminders, rebook, review, win-back), simulated dispatch, reporting (`/admin/marketing`).

**Step 19B (Module 10B):** Marketing automation & delivery operations — workflow journeys, execution engine, suppressions/unsubscribe (`/lift`), richer message delivery states, domain trigger integrations (client created, consent granted/withdrawn, appointment completed/no-show, membership started/cancelled, birthday admin-run), operational admin UI (`/admin/marketing/workflows`, `/executions`, `/messages`, `/suppressions`).

**Step 20A (Module 11A):** Notifications & communications foundation (backend only) — operational communication log separate from marketing (`notifications_templates`, `notifications_messages`, `notification_preferences`, `notification_automation_settings`), simulation-first dispatch, consent-projected preferences, client communication timeline, operational reporting (`/api/v1/admin/notifications/*`).

**Step 20B (Module 11A):** Notifications admin UI (frontend only) — operational communications console at `/admin/notifications` (overview, messages log + detail + manual send, templates, per-client preferences with CRM-consent sync, tenant settings) plus a Communications tab on the client detail page. Clearly separated from Marketing; delivery shown as simulated.

**Step 21A (Module 12A):** Analytics & reporting foundation (backend only) — read-only cross-domain operational KPIs at `/api/v1/admin/analytics/*` (overview, bookings, revenue, clients, inventory, communications) with date-range / location / provider filtering. No materialized tables; aggregates over existing domain data.

**Step 21B (Module 12A):** Analytics admin UI (frontend only) — operational analytics dashboard at `/admin/analytics` (overview + bookings, revenue, clients, inventory, communications subpages) consuming the Step 21A API. KPI stat cards, breakdown tables, and zero-filled daily-series lists with date/location/provider filters. No chart library; cards + tables only.

**Step 22A (Module 12B):** Analytics exports & saved reports (backend only) — saved report presets (`analytics_saved_reports`) and synchronous CSV/JSON export jobs (`analytics_export_jobs`) at `/api/v1/admin/analytics/saved-reports` and `/api/v1/admin/analytics/exports`, gated by `analytics.exports.manage`. Exports run inline (no queues), persist files to local storage, and are downloadable. Scheduling fields are persisted as config only; PDF/XLSX and background/emailed delivery are deferred.

**Step 22B (Module 12B):** Analytics exports & saved reports (frontend only) — admin UI at `/admin/analytics/reports` (saved presets CRUD, archive, run export) and `/admin/analytics/exports` (ad-hoc export panel + export history with download). Consumes the Step 22A API; scheduling is config-only in the UI.

**Step 23A (Module 13A):** Provider integrations foundation (backend only) — shared `provider_accounts`, `provider_delivery_attempts`, and `provider_webhook_events` tables with admin API at `/api/v1/admin/integrations/*` and public webhook intake at `/api/v1/integrations/webhooks/{driver}`. Notifications, Marketing, and Payments dual-log provider delivery attempts while preserving simulation-first domain behaviour.

**Step 23B (Module 13A):** Provider integrations admin UI (frontend only) — admin surfaces at `/admin/integrations` (overview, provider accounts, delivery attempts, webhook events) consuming the Step 23A API. Simulation-first banner, credential redaction respected, overview derived from list endpoints.

## Quick start

```bash
# Backend
cd backend && composer install && cp .env.example .env
php artisan key:generate && php artisan migrate && php artisan db:seed
php artisan serve

# Frontend (new terminal)
cd frontend && npm install && cp .env.example .env.local && npm run dev
```

Open `http://localhost:3000/health` — verifies frontend → backend connectivity.

Demo credentials: `owner@demo.neatmeet.local` / `password`

## Tests

```bash
cd backend && php artisan test
cd frontend && npm test && npm run build
```

Full guide: [docs/local-development.md](docs/local-development.md) · Closure: [docs/final-platform-closure.md](docs/final-platform-closure.md)

## Governing documents

- [Product charter](product/01-product-vision/neatmeet-os-product-charter.md)
- [Implementation roadmap](product/04-delivery/implementation-roadmap.md)
- [System architecture](architecture/system-architecture.md)
- [Technology decisions](architecture/technology-decisions.md)

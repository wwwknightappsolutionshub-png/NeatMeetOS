# NeatMeet OS — System Architecture

## Purpose

This document defines the overall technical architecture of **NeatMeet OS** — a multi-tenant salon operating system supporting solo salonists, freelancers, chair/room renters, hybrid salons, and multi-location groups.

---

## A. System Overview

### High-level architecture

NeatMeet OS is a **modular monolith** with a **separate Next.js frontend** and **Laravel API backend**, connected via versioned REST JSON APIs and WebSocket channels for real-time operational surfaces.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Client Surfaces (Next.js PWA)                        │
│  Owner Admin │ Front Desk │ Provider Workspace │ Client Portal │ Widgets    │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │ HTTPS / WSS
┌──────────────────────────────────▼──────────────────────────────────────────┐
│                    API Gateway Layer (Nginx → Laravel)                       │
│         REST /api/v1/*  │  Sanctum Auth  │  Rate Limiting  │  Reverb WSS   │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
┌──────────────────────────────────▼──────────────────────────────────────────┐
│                   Laravel Modular Monolith (Domain Modules)                  │
│  Identity │ CRM │ Booking │ POS │ Payments │ Inventory │ Staff │ ...        │
│         Domain Services  →  Events  →  Listeners  →  Jobs                   │
└───────┬─────────────────┬─────────────────┬─────────────────┬──────────────┘
        │                 │                 │                 │
        ▼                 ▼                 ▼                 ▼
   PostgreSQL          Redis            S3 Storage      External Providers
   (transactional)   (cache/queue)    (files/media)   (Stripe, Xero, etc.)
```

### Architectural style: modular monolith

**Chosen:** Modular monolith (not microservices).

**Rationale:**

| Factor | Modular monolith advantage |
|---|---|
| Commerce cluster (Booking + POS + Payments + Inventory + Memberships) | Single ACID transaction boundary for checkout, stock decrement, payment capture |
| Team size / ops | One deployable API, one migration stream, simpler VPS hosting |
| Domain boundaries | Modules enforce separation without network latency and distributed tracing overhead |
| Future scale | Modules can extract to services later if a domain truly outgrows the monolith |

Microservices are deferred until proven necessary (e.g., analytics warehouse isolation).

### Multi-tenant salon operations

Every request resolves:

1. **Tenant** (salon organization)
2. **Location** (branch/site — optional for solo operators)
3. **Actor** (staff user or client)
4. **Permissions** (role + workspace relationship)

Operational data is scoped by `tenant_id` and often `location_id`. Freelancers and chair renters operate within a tenant's location with workspace-scoped permissions. See `tenancy-auth-permissions.md`.

---

## B. Major Application Surfaces

| Surface | Users | Primary modules | Delivery |
|---|---|---|---|
| **Owner / admin web app** | Owners, managers, finance | Tenant, Analytics, Staff, Marketing, Integrations | Next.js `/admin/*` |
| **Front desk / reception workspace** | Receptionists, managers | Booking, POS, CRM, Waitlist | Next.js `/desk/*` |
| **Stylist / therapist / freelancer workspace** | Employees, chair renters, room renters | Booking (own calendar), CRM (scoped clients), Consultation, Commission view | Next.js `/provider/*` |
| **Client booking / self-service portal** | End clients | Booking, Memberships, Loyalty, Deposits | Next.js `/book/*`, `/portal/*` |
| **Public booking widgets** | Anonymous + returning clients | Online booking, deposits | Next.js embeddable routes + iframe SDK |
| **PWA / mobile companion** | Staff on floor, providers | Calendar, check-in, POS lite | Next.js PWA; native shell optional later |
| **Background workers** | System | Reminders, sync, webhooks, reports | Laravel queue workers + Horizon |
| **Platform super-admin** | NeatMeet OS operators | Tenant provisioning, plan management | Separate guarded routes (extension or Module 1 subset) |

### PWA strategy

Front desk and provider surfaces are PWA-enabled for tablet/phone use on salon floor. Offline tolerance is limited to read-cache of today's calendar — writes require connectivity. Client booking portal is online-first with optimistic UI where safe.

---

## C. Core Architectural Building Blocks

### API layer

- Versioned REST: `/api/v1/{domain}/{resource}`
- Thin controllers delegating to domain services
- Form request validation at boundary
- API resources for consistent JSON shapes
- OpenAPI spec published for Integrations module

### Auth layer

- Sanctum for staff SPA sessions
- Separate client authentication for portal
- Middleware chain: `ResolveTenant` → `Authenticate` → `Authorize` → controller
- See `tenancy-auth-permissions.md`

### Domain modules

13 roadmap modules map to Laravel domain modules under `backend/app/Domains/{DomainName}/`:

```
Domains/
  Identity/
  Crm/
  Booking/
  Consultation/
  Pos/
  Payments/
  Inventory/
  Staff/
  Marketing/
  Memberships/
  Ecommerce/
  Analytics/
  Integrations/
```

Each domain contains: `Models/`, `Services/`, `Repositories/`, `Events/`, `Listeners/`, `Policies/`, `Http/Controllers/`, `Database/Migrations/`.

### Eventing / messaging

- Laravel domain events for cross-module communication
- Events are synchronous for in-process side effects; queued listeners for async work
- Event catalog documented per domain (see `data-architecture.md`)
- No direct cross-domain model access

### File storage

- S3-compatible bucket per environment, path prefix per tenant: `{tenant_id}/{domain}/{entity_id}/`
- Pre-signed URLs for client uploads (photos, signatures)
- Virus scan hook (extension) for uploads

### Background jobs

- Reminders, marketing sends, integration sync, report generation, webhook retries
- Redis queue with Horizon dashboard
- Job middleware attaches tenant context and correlation ID

### Notifications

- Unified `NotificationService` dispatching email, SMS, in-app, push (future)
- Provider adapters in Integrations domain
- Templates owned by Marketing; delivery infrastructure shared

### Analytics ingestion

- Domain events → `analytics_events` table (append-only)
- Scheduled aggregation jobs → read model tables
- Dashboard queries hit read models, not raw transactional scans

### Integration adapters

- Provider pattern: `PaymentProviderInterface`, `AccountingProviderInterface`, etc.
- Anti-corruption layer translates external payloads to internal DTOs
- See `integration-architecture.md`

---

## D. Domain / Module Alignment

| # | Roadmap module | Domain folder | Primary surfaces |
|---|---|---|---|
| 1 | Tenant / account management | `Identity` | Admin |
| 2 | Booking & scheduling | `Booking` | Desk, Provider, Client widget |
| 3 | Client CRM | `Crm` | Desk, Provider, Admin |
| 4 | Consultation & treatment records | `Consultation` | Provider, Desk |
| 5 | POS & checkout | `Pos` | Desk |
| 6 | Payments & billing | `Payments` | Desk, Client portal, Admin |
| 7 | Inventory | `Inventory` | Admin, Desk |
| 8 | Staff, rota & compensation | `Staff` | Admin, Provider |
| 9 | Marketing automation | `Marketing` | Admin |
| 10 | Memberships / loyalty / packages | `Memberships` | Desk, Client portal |
| 11 | Retail ecommerce | `Ecommerce` | Client storefront |
| 12 | Analytics & BI | `Analytics` | Admin |
| 13 | Integrations | `Integrations` | Admin |

### Commerce cluster (tightly coupled)

These domains share contracts and must be planned together:

```
Booking ←→ Payments (deposits)
Booking ←→ POS (service checkout)
Booking ←→ Inventory (service consumption)
POS ←→ Payments (capture, refund)
POS ←→ Inventory (retail sale, stock decrement)
POS ←→ Memberships (redemption, gift cards)
Memberships ←→ Payments (subscriptions, wallets)
```

See `../product/04-delivery/implementation-roadmap.md` for sequencing.

---

## E. Recommended Repo / App Structure

Monorepo at repository root:

```
neatmeet-os/
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Domains/            # 13 domain modules
│   │   ├── Http/               # Shared middleware, kernel
│   │   ├── Providers/
│   │   └── Support/            # Shared helpers, traits (TenantScoped, etc.)
│   ├── config/
│   ├── database/
│   │   └── migrations/         # Domain migrations namespaced or prefixed
│   ├── routes/
│   │   ├── api.php             # Includes domain route files
│   │   └── channels.php        # Reverb channels
│   ├── tests/
│   │   ├── Feature/            # Per-domain feature tests
│   │   └── Unit/
│   └── composer.json
├── frontend/                   # Next.js PWA
│   ├── app/
│   │   ├── (admin)/            # Owner admin layout
│   │   ├── (desk)/             # Front desk layout
│   │   ├── (provider)/         # Stylist/freelancer layout
│   │   ├── (portal)/           # Client portal
│   │   ├── (book)/             # Public booking
│   │   └── api/                # BFF routes if needed (minimal)
│   ├── components/
│   │   ├── ui/                 # Shared design system
│   │   └── domains/            # Domain-specific components
│   ├── lib/
│   │   ├── types.ts            # API types
│   │   └── api/                # API client per domain
│   ├── services/               # Frontend service layer
│   └── package.json
├── packages/                   # Optional shared packages (future)
│   └── api-contracts/          # OpenAPI spec, generated types
├── docs/                       # Product + architecture docs (current)
├── product/
├── architecture/
├── docker-compose.yml          # Local: postgres, redis, minio, mailpit
├── .github/workflows/          # CI
└── README.md
```

### Module internal structure (example: `Domains/Booking/`)

```
Booking/
├── Models/
├── Services/
│   ├── AppointmentService.php
│   ├── AvailabilityService.php
│   └── BookingRulesEngine.php
├── Repositories/
├── Events/
│   ├── AppointmentConfirmed.php
│   └── AppointmentCompleted.php
├── Listeners/
├── Policies/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Database/Migrations/
└── routes.php
```

---

## F. Deployment Shape

### Production topology (VPS / cloud VM)

```
                    ┌─────────────┐
                    │   Nginx     │
                    │  (TLS/SSL)  │
                    └──────┬──────┘
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │  Next.js   │  │ PHP-FPM    │  │  Reverb    │
    │  (PM2)     │  │ (Laravel)  │  │  (WS)      │
    └────────────┘  └─────┬──────┘  └────────────┘
                          │
         ┌────────────────┼────────────────┐
         ▼                ▼                ▼
  ┌────────────┐   ┌────────────┐   ┌────────────┐
  │ PostgreSQL │   │   Redis    │   │ S3 / MinIO │
  └────────────┘   └─────┬──────┘   └────────────┘
                         │
                  ┌──────▼──────┐
                  │   Horizon   │
                  │  (workers)  │
                  └─────────────┘
```

| Component | Role | Scaling note |
|---|---|---|
| **Next.js** | SSR + static assets for all surfaces | PM2 cluster or single instance initially |
| **Laravel API** | Business logic, REST, auth | PHP-FPM workers scale horizontally |
| **Reverb** | Live calendar, waitlist, POS updates | Single instance OK initially |
| **Horizon workers** | Async jobs | Scale worker count by queue depth |
| **PostgreSQL** | System of record | Vertical scale; read replica later for analytics |
| **Redis** | Cache, sessions, queues, pub/sub | Single instance with persistence |
| **S3** | Files, images, exports, receipts | Unlimited object scale |
| **Sentry** | Error tracking | SaaS |
| **Backups** | pg_dump scheduled + S3 retention | Daily minimum |

### Environment separation

Local (Docker Compose) → Test (CI) → Staging (production mirror) → Production

See `observability-and-ops.md` for CI/CD and monitoring detail.

---

## Related Documents

| Document | Purpose |
|---|---|
| `technology-decisions.md` | Stack commitment |
| `tenancy-auth-permissions.md` | Tenant and auth model |
| `data-architecture.md` | Data ownership and events |
| `domain-boundaries.md` | Bounded contexts |
| `../product/04-delivery/implementation-roadmap.md` | Delivery phases |

---

*NeatMeet OS system architecture — Step 2 blueprint.*

# NeatMeet OS — Technology Decisions

## Purpose

This document **commits** to the recommended implementation stack for NeatMeet OS. Later build steps must not leave stack choices ambiguous.

**Decision date:** Step 2 architecture blueprint  
**Status:** Approved primary direction

---

## A. Chosen Stack

| Layer | Choice | Version direction |
|---|---|---|
| **Frontend framework** | Next.js (App Router) + React + TypeScript | Next.js 15+, React 19+ |
| **Frontend delivery** | PWA with responsive web surfaces; optional native wrappers later | `next-pwa` or equivalent |
| **Backend framework** | Laravel (PHP) modular monolith | PHP 8.3+, Laravel 11+ |
| **API approach** | REST JSON API (versioned) + internal domain events | `/api/v1/*` |
| **Real-time** | Laravel Reverb (WebSockets) for calendar/POS live updates | Reverb + Echo client |
| **Transactional database** | PostgreSQL | 16+ |
| **Cache / session / queue broker** | Redis | 7+ |
| **Background jobs** | Laravel Queues (Redis driver) + Horizon for monitoring | Horizon |
| **Object / file storage** | S3-compatible (AWS S3 production; MinIO local) | Flysystem S3 adapter |
| **Search (phase 1)** | PostgreSQL full-text + indexed filters | Meilisearch optional extension |
| **Auth (staff/admin)** | Laravel Sanctum (SPA token / cookie session hybrid) | Sanctum |
| **Auth (client portal)** | Sanctum + separate client guard; magic link optional | Distinct `Client` model |
| **Multitenancy** | Single database, shared schema, `tenant_id` (+ `location_id`) row scoping | Global scopes + policies |
| **Backend testing** | Pest (PHPUnit-compatible) feature + unit tests | Pest 3+ |
| **Frontend testing** | Vitest (unit) + Playwright (E2E critical flows) | — |
| **API contract typing** | OpenAPI-generated or hand-maintained types consumed by frontend `lib/types.ts` | — |
| **CI/CD** | GitHub Actions → build, test, deploy | — |
| **Deployment model** | VPS or cloud VM: Nginx, PHP-FPM, PM2 (Next.js), PostgreSQL, Redis, workers | Docker Compose for local |
| **Observability** | Structured JSON logs, Sentry (errors), health endpoints; Prometheus/Grafana optional | See `observability-and-ops.md` |
| **Email / SMS** | Queue-backed notifications; provider adapters (Mailgun/Postmark, Twilio) | Integration adapters |

---

## B. Why This Stack Fits NeatMeet OS

### Multi-tenant SaaS needs

Laravel provides mature patterns for authentication, authorization, queues, migrations, and multi-tenant row scoping. PostgreSQL offers strong relational integrity for financial, inventory, and booking data — critical for a salon OS handling money and stock.

### Booking + POS + payments + CRM complexity

A **modular monolith** keeps transactional consistency (appointments, orders, payments, stock movements) in one deployable unit while enforcing domain boundaries via modules, services, and events. Microservices would add distributed-transaction risk for checkout, deposits, and inventory consumption without proportional benefit at initial scale.

### Modular domain boundaries

Laravel modules (domain folders or `nwidart/laravel-modules` style) map cleanly to the 13 roadmap modules and bounded contexts in `domain-boundaries.md`. Each module owns migrations, services, events, and routes within the monolith.

### AI-assisted implementation practicality in Cursor

Laravel + Next.js is well-represented in training data and prior KhayaOS-style delivery patterns. Cursor agents can implement Controller → Service → Repository flows, PHPUnit/Pest tests, and typed Next.js pages with predictable conventions — reducing architecture drift during AI-assisted delivery.

### Developer productivity

- Laravel: rapid CRUD with discipline, excellent queue/event system, Sanctum, migrations
- Next.js: SSR for public booking widgets (SEO), App Router layouts per workspace surface, PWA offline for front desk
- TypeScript on frontend: typed API contracts, fewer integration bugs across 13 modules

### Production maintainability

Single-repo monorepo with `backend/` and `frontend/` is operationally simple: one release train, shared versioning, unified CI. Redis queues handle reminders, sync jobs, and marketing sends. Horizon surfaces job failures.

### Salon-specific operating models

PostgreSQL relational models suit chair/room/workspace hierarchies, commission splits, chair-rent settlements, and multi-location reporting without document-store ambiguity.

---

## C. Rejected Alternatives

| Alternative | Why not primary |
|---|---|
| **Microservices** | Checkout, deposits, inventory consumption, and memberships require atomic consistency; operational overhead too high for initial team size |
| **NestJS (Node) backend** | Viable, but weaker out-of-box for migrations, queues, and RBAC patterns; Laravel better fit for complex business rules + audit |
| **Django / Python** | Strong option; less alignment with established delivery patterns and PHP hosting on target VPS stack |
| **Supabase / Firebase-first** | Insufficient control for POS, commission, and multi-tenant RBAC complexity; vendor lock-in for core business logic |
| **GraphQL primary API** | REST simpler for integrations, webhooks, and mobile; GraphQL adds complexity without clear salon-OS benefit |
| **Schema-per-tenant PostgreSQL** | Migration and ops burden at scale; row-level `tenant_id` scoping sufficient with strict global scopes |
| **MongoDB primary** | Financial and inventory data need ACID transactions and relational reporting |
| **Separate repos per module** | Breaks atomic releases and increases integration friction for tightly coupled commerce cluster |

---

## D. Non-Negotiable Engineering Standards

These standards are implied by the chosen stack and are **mandatory** for all module work:

### Typed APIs and contracts

- All API responses use consistent JSON envelope patterns
- Frontend types in `frontend/lib/types.ts` (or generated from OpenAPI)
- No untyped `any` crossing module boundaries in new code

### Migrations

- Every schema change via Laravel migrations
- Migrations are reversible where practical
- Seeders for dev/demo only — never production data paths

### Modular domain boundaries

- Business logic in domain services, not controllers
- Cross-domain access via published service interfaces or domain events — not foreign table queries across modules
- Align code modules to `domain-boundaries.md`

### Test strategy

- PHPUnit/Pest feature tests for every API endpoint and permission rule
- Unit tests for commission, tax, duration, and pricing calculations
- Playwright E2E for critical flows: book → check-in → checkout → receipt
- `php artisan test` and `npm run build` must pass before module acceptance

### Shared component strategy

- KhayaOS-style UI primitives: Card, Button, form patterns, Anek font (or NeatMeet OS design tokens once defined)
- Workspace-specific layouts: owner admin, front desk, provider, client portal

### Background job handling

- All external API calls (payments, SMS, email, sync) via queued jobs
- Idempotency keys on payment and webhook handlers
- Failed jobs monitored via Horizon + alerting

### Security baseline

- Tenant isolation on every query via global scope middleware
- Sanctum token abilities scoped by role
- Secrets in environment only; never committed

### Additive API evolution

- `/api/v1/` — breaking changes require `/api/v2/`
- Webhook payloads versioned

---

## Related Documents

| Document | Purpose |
|---|---|
| `system-architecture.md` | Overall architecture and repo structure |
| `tenancy-auth-permissions.md` | Multitenancy and auth detail |
| `data-architecture.md` | Storage and entity clusters |
| `integration-architecture.md` | External system adapters |
| `observability-and-ops.md` | Production operations |

---

*This stack decision is binding for NeatMeet OS implementation unless explicitly revised in a future architecture amendment.*

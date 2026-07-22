# NeatMeet OS — Repository Structure

Step 3 bootstrap layout for the **NeatMeet OS** modular monolith (Laravel API + Next.js PWA).

## Top-level layout

```
NeatMeetOS/
├── backend/                 # Laravel 13 API (modular monolith)
├── frontend/                # Next.js 16 App Router PWA
├── docker/                  # Docker Compose + Dockerfiles
├── docs/                    # Developer documentation
├── product/                 # Product vision, scope, delivery (Step 1–2)
├── architecture/            # Technical architecture (Step 2)
├── .github/workflows/       # CI pipelines
└── README.md
```

## Why `backend/` + `frontend/` (not `apps/`)

Step 2 `system-architecture.md` specifies `backend/` and `frontend/` at repo root. This matches the KhayaOS monorepo convention and keeps paths stable for CI and Docker volume mounts.

## Backend domain layout

```
backend/app/
├── Domains/
│   ├── Identity/          # Module 1 — implemented skeleton (Step 3)
│   ├── Crm/               # Scaffold only
│   ├── Booking/
│   ├── Consultation/
│   ├── Pos/
│   ├── Payments/
│   ├── Inventory/
│   ├── Staff/
│   ├── Marketing/
│   ├── Memberships/
│   ├── Ecommerce/
│   ├── Analytics/
│   └── Integrations/
└── Shared/
    ├── Middleware/        # CorrelationId, ResolveTenant
    ├── Tenancy/           # TenantContext, BelongsToTenant, HasUuid
    └── Support/           # ApiResponse
```

Each domain folder contains:

- `Models/` — Eloquent models (Identity populated in Step 3)
- `Services/` — business logic (Module implementation)
- `Http/Controllers/` — thin API controllers
- `Events/`, `Policies/` — domain events and authorization

## Frontend workspace routes

| Route group | Path | Surface |
|---|---|---|
| `(public)` | `/health` | Stack proof |
| `(auth)` | `/login` | Staff auth shell |
| `(admin)` | `/admin/dashboard` | Owner/admin shell |
| `(desk)` | `/desk` | Front desk shell |
| `(provider)` | `/provider` | Stylist/freelancer shell |

Future: `(portal)`, `(book)` for client surfaces.

## API versioning

All routes: `/api/v1/*` plus Laravel built-in `/up` health probe.

---

*Updated in Step 3 bootstrap.*

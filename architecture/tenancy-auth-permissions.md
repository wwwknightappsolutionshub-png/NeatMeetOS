# NeatMeet OS — Tenancy, Auth & Permissions

## Purpose

This document defines the tenancy, identity, and authorization model for **NeatMeet OS**, including support for solo salonists, freelance salonists, chair renters, room renters, hybrid salons, and multi-location groups.

---

## A. Multi-Tenancy Model

### Tenant = salon organization

A **tenant** represents a billable salon business account on the NeatMeet OS platform:

- One subscription plan per tenant
- One or more **locations** (branches/sites)
- Shared client database per tenant (with location scoping where configured)
- Centralized branding with per-location overrides optional
- Platform audit logs scoped to tenant

### Multi-location modeling

| Concept | Description |
|---|---|
| **Tenant** | Organization / salon group (billing entity) |
| **Location** | Physical site: boutique salon, barber shop, treatment studio |
| **Workspace** | Bookable unit within a location: chair, room, station, seat, slot |

Multi-location salons share tenant-level CRM and reporting with location-filtered operations. Cross-location booking is configurable per tenant.

### Solo and freelancer fit

| Business type | Tenant structure |
|---|---|
| **Solo salonist** | Single tenant, single location, single workspace (implicit), owner = sole provider |
| **Freelance salonist** | May be tenant owner of own micro-business OR team member / renter within another tenant |
| **Chair / room renter** | Team member with `renter` relationship; workspace assignment; chair-rent billing in Staff domain |
| **Hybrid salon** | Employed staff + renters under same location; mixed permission and commission models |

A freelancer operating independently gets their own tenant. A freelancer renting a chair operates inside the host salon's tenant with scoped access.

### Database strategy: single database, shared schema, row-level scoping

**Chosen approach:** Single PostgreSQL database, shared schema, `tenant_id` on all tenant-owned tables.

| Approach | Decision |
|---|---|
| Single DB + `tenant_id` | **Primary** — simpler ops, unified migrations, strong for SaaS up to large scale |
| Schema-per-tenant | Rejected — migration multiplication, connection complexity |
| Database-per-tenant | Rejected — ops burden; reserved for enterprise extension only |

### Tenant isolation enforcement

1. **Global scope** — Eloquent `TenantScope` applied to all tenant-owned models; unscoped queries require explicit `withoutGlobalScopes()` with audit justification
2. **Middleware** — `ResolveTenant` sets tenant from subdomain, custom domain, or authenticated user's membership
3. **Policies** — Every mutation checks `tenant_id` match on the target model
4. **Queue jobs** — `TenantContext` serialized into job payload; restored on handle
5. **Files** — S3 paths prefixed with `tenant_id`
6. **Tests** — Feature tests must include cross-tenant leakage assertions

---

## B. Business Hierarchy Model

```
Tenant (Organization)
 └── Location (Branch / Site)
      ├── Workspace (Chair / Room / Station / Seat)
      │    ├── workspace_type: chair | room | station | seat | slot
      │    └── assignable_to: staff_member | renter | shared
      ├── Service catalog (location-scoped or tenant-wide)
      └── Team members
           ├── employment_type: owner | employee | freelancer | chair_renter | room_renter
           └── workspace_assignments[]
```

### Entity definitions

| Entity | Owns | Examples |
|---|---|---|
| **Organization / tenant** | Subscription, global branding, cross-location policies | "Glow Group Ltd" |
| **Location / branch** | Address, hours, local resources, local tax settings | "Glow Hair — High Street" |
| **Workspace** | Bookable resource for calendar allocation | Chair 3, Treatment Room A, Nail Station 2 |
| **Team member** | User's relationship to tenant + role + employment type | Sarah (chair renter), James (employee stylist) |
| **Client** | End customer of the tenant | Jane Doe |

### Workspace / booking models

Booking module allocates **workspaces** alongside **staff** for:

- Chair-based salons (stylist + chair)
- Room-based studios (therapist + treatment room)
- Slot-based scheduling (time slots without named chair)
- Shared resources (wash stations, nail tables)

Rules engine can require workspace availability, staff availability, or both.

---

## C. Identity Model

### User account types

| Type | Model | Portal |
|---|---|---|
| **Staff / team user** | `User` + `TeamMember` | Admin, desk, provider apps |
| **Client / customer** | `Client` (with optional `ClientAuth` for portal login) | Client portal, booking widget |
| **Platform admin** | `User` with `platform_admin` flag | Super-admin only |

One human may have:

- `User` account for staff login across tenants they belong to
- `Client` records in multiple tenants (no shared client identity across tenants by default)

### Role personas

| Persona | Typical roles | Surfaces |
|---|---|---|
| **Owner** | `owner` | Full admin + financial |
| **Manager** | `manager` | Operations, rota, reports (configurable) |
| **Receptionist** | `receptionist` | Desk, POS, booking, CRM (no payroll) |
| **Employee stylist / therapist** | `provider` | Own calendar, clients, consultation |
| **Freelancer / chair renter / room renter** | `renter` | Scoped calendar, own clients (policy-dependent), commission/rent view |
| **Finance / admin user** | `finance` | Reports, exports, payouts; no clinical |
| **Client / customer** | `client` | Portal only |

### Employment type vs role

- **Role** = permission bundle (what you can do)
- **Employment type** = business relationship (how you're compensated, workspace assignment)

A chair renter has `employment_type: chair_renter` and typically `role: renter` or `role: provider` with restricted admin access.

---

## D. Auth Model

### Primary approach

**Laravel Sanctum** for staff SPA authentication:

- Cookie-based session for same-origin Next.js ↔ Laravel (CSRF protected)
- API tokens for mobile/companion apps and integrations (scoped abilities)

### Client auth

Separate guard (`client`) for customer portal:

- Email + password OR magic link (passwordless)
- Phone OTP (extension scope, region-dependent)
- Booking widget may use guest checkout with optional account creation

### Staff vs client login

| Aspect | Staff | Client |
|---|---|---|
| Guard | `web` / Sanctum | `client` |
| Login URL | `/login` | `/portal/login` |
| Tenant resolution | User's team membership | Client's `tenant_id` + optional location |
| Token abilities | Role-based | Client-scoped (own bookings, loyalty) |

### Password reset / MFA

- Password reset via signed email link (both staff and client)
- MFA (TOTP) — **recommended for owner/finance roles**; enforced per tenant policy (Phase 2+)
- Magic link for client portal reduces friction

### Session lifecycle

- Staff sessions: 8-hour default, sliding expiration, revoke on password change
- Client sessions: 30-day remember-me optional
- Sanctum token expiration configurable per ability

---

## E. Authorization Model

### RBAC + policies

- **Roles** are tenant-defined bundles of **permissions**
- Default role templates seeded per business type (solo, boutique, hybrid, multi-location)
- **Laravel Policies** per model: `$user->can('update', $appointment)`
- Permissions namespaced: `{module}.{action}` e.g. `booking.create`, `pos.refund`, `staff.view_payroll`

### Permission dimensions

Permissions may vary by:

| Dimension | Example |
|---|---|
| **Organization** | Owner sees all locations; renter sees assigned location only |
| **Location** | Receptionist scoped to Location A |
| **Workspace relationship** | Renter sees only own chair's calendar |
| **Role** | Finance sees payouts; provider does not |
| **Module** | Marketing module gated by subscription plan |
| **Ownership** | Provider sees own clients; manager sees all clients |

### Chair renter / freelancer specifics

| Rule | Behavior |
|---|---|
| Calendar | Renter sees own appointments + shared location calendar (read-only config) |
| Clients | Default: clients they served; configurable shared CRM pool |
| POS | Renter may checkout own services; retail permissions configurable |
| Commission | Renter sees own commission; not payroll for other staff |
| Chair rent | Renter sees own rent invoices; owner sees all |

### Plan-based feature flags

Subscription plan maps to enabled modules and limits:

- Max locations, max staff, marketing sends/month
- Enforced in **middleware + service layer** (not UI-only)

---

## F. Auditability & Security

### Audit logs

All consequential mutations write to `audit_logs`:

```
tenant_id, actor_type, actor_id, action, entity_type, entity_id,
old_values (json), new_values (json), ip_address, user_agent, created_at
```

Audited actions include: booking status changes, POS refunds, permission changes, client consent updates, payment captures, inventory adjustments, impersonation.

### Impersonation

Platform super-admin may impersonate tenant owner for support:

- Requires explicit `platform.impersonate` permission
- Full audit trail with `impersonated_by`
- Banner shown in UI during impersonation
- Cannot access payment credentials or change passwords

### Data access controls

- PII fields (phone, email, notes) require `crm.view_pii` permission
- Treatment photos and consultation forms require `consultation.view`
- Financial data requires `finance.view` or `pos.view`
- Export actions logged separately

### Security baseline

- HTTPS everywhere; HSTS in production
- Rate limiting on auth and public booking endpoints
- CORS restricted to known frontend origins
- SQL injection prevented via Eloquent; raw queries banned in domain code without review
- File upload type and size validation; tenant-scoped storage paths

---

## Related Documents

| Document | Purpose |
|---|---|
| `system-architecture.md` | Overall architecture |
| `data-architecture.md` | Entity clusters |
| `domain-boundaries.md` | Identity domain ownership |
| `technology-decisions.md` | Sanctum, PostgreSQL choices |

---

*NeatMeet OS tenancy and auth model — Step 2 blueprint.*

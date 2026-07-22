# Identity domain — Module 1 complete

**Roadmap module:** 1 — Tenant / account management (1A + 1B)

## Module 1A (Step 4)

- Organization profile API + admin UI
- Location CRUD + activate/deactivate
- Location **opening hours** (`opening_hours` JSON — weekly windows / closed days; enforced in booking scheduling + online slots)
- Workspace CRUD (chair/room/station/seat/slot) + activate/deactivate
- Team member admin (employment types including chair/room renter)
- Basic team member role assignment
- Audit log writes on admin mutations

## Module 1B (Step 5)

- Branding settings (tenant `settings.branding` JSON — logo URL/path, colors, receipt name, support contact)
- Role CRUD + archive, permission catalogue, role permission assignment
- `identity.access.manage` permission for access governance
- Subscription plan catalogue + `tenant_subscriptions` (status, trial/period dates, provider placeholders)
- Tenant subscription visibility (no payment collection)
- Audit log listing + filters (`identity.audit.view`)

## Permissions

| Permission | Purpose |
|---|---|
| `identity.view` | Read org, branding, locations, workspaces, team, roles, subscription |
| `identity.manage` | Mutate org, branding, locations, workspaces, team |
| `identity.access.manage` | Role CRUD, role permissions, team role assignment |
| `identity.audit.view` | Audit log visibility |

## Deferred

- Full public homepage CMS / section builder (branding tokens + logo drive `/book/{slug}` today)
- Logo file upload pipeline (use `logo_url` until storage module)
- Stripe/payment orchestration (Payments module)
- User invitations / password reset
- Platform tenant provisioning / suspend / impersonation (listing + KPIs shipped under `/platform`)
- Plan upgrade/checkout flows

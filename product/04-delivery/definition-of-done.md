# NeatMeet OS Definition of Done

## Purpose

This document defines what **"done"** means for any NeatMeet OS module or sub-capability. It is the acceptance standard for human and AI-assisted delivery.

**A module cannot be marked complete unless all relevant sections below pass.** Partial completion must be tracked explicitly — never implied as done.

---

## Scope Reference

Before assessing done-ness, confirm the module and its sub-capabilities against `../01-product-vision/scope-lock.md`. Every sub-capability listed there must be accounted for.

---

## 1. Functional Completion

| Criterion | Requirement |
|---|---|
| **Scoped capabilities** | All sub-capabilities for the module are implemented — none skipped or deferred without documented scope change |
| **No placeholder screens** | Every required screen renders real data from backend services — no static mock UI presented as functional |
| **No fake dependencies** | No hardcoded data, local JSON fixtures, or stub services substituting for upstream modules in production code paths |
| **Business rules** | All business rules for the module are implemented in service layer logic — not only documented or UI-validated |
| **State transitions** | All entity state machines are complete (e.g., booking: requested → confirmed → checked-in → completed → no-show / cancelled) |
| **Permissions enforced** | Every action checks role/permission requirements — unauthorized users cannot perform restricted operations via UI or API |
| **Edge cases** | Documented edge cases (concurrency, partial failure, empty states, boundary values) are handled — not left as unhandled exceptions |

### Functional completion gate

- [ ] Sub-capability checklist from scope-lock.md is 100% implemented
- [ ] No TODO/FIXME/HACK markers remain in production paths for this module
- [ ] Demo or seed data is clearly separated from production code paths

---

## 2. Backend Completion

| Criterion | Requirement |
|---|---|
| **Schema and migrations** | Database schema is complete with migrations; indexes support query patterns; foreign keys and constraints are correct |
| **Service logic** | Business logic lives in service classes (Controller → Service → Repository pattern); controllers remain thin |
| **Validations** | Input validation at API boundary and business rule validation in services; meaningful error messages returned |
| **Audit logging** | Consequential mutations (create, update, delete, status change, financial action) write audit log entries with actor, tenant, timestamp, and change detail |
| **Events emitted** | Domain events are emitted at correct lifecycle points for downstream modules (booking confirmed, order paid, stock adjusted, etc.) |
| **Authorization** | Policy/gate checks on every endpoint and service entry point for sensitive operations |
| **Tenant isolation** | Every query scopes by tenant (and location where applicable); cross-tenant data leakage is impossible by construction |
| **Idempotency** | Payment and other retry-sensitive operations handle duplicate requests safely where applicable |

### Backend completion gate

- [ ] Migrations run cleanly on fresh and existing databases
- [ ] Service layer has unit test coverage for core business rules
- [ ] Authorization tests confirm denied access for unauthorized roles
- [ ] Tenant isolation verified — queries cannot return other tenants' data

---

## 3. Frontend Completion

| Criterion | Requirement |
|---|---|
| **Required screens** | All screens required by module scope are implemented and reachable via navigation |
| **Loading states** | Async data fetches show loading indicators — no blank screens during fetch |
| **Empty states** | Lists and dashboards show meaningful empty states with guidance — not broken layouts |
| **Error states** | API failures surface user-friendly error messages; retry paths provided where appropriate |
| **Validation** | Form validation mirrors backend rules; inline errors shown before submission |
| **Responsive behavior** | Layouts function on mobile, tablet, and desktop breakpoints relevant to the workflow |
| **Accessibility baseline** | Semantic HTML, keyboard navigability for core flows, sufficient color contrast, form labels associated with inputs |
| **Consistent UI** | Uses established design system components (buttons, cards, typography, spacing) — no one-off unstyled elements |

### Frontend completion gate

- [ ] All module screens implemented per scope
- [ ] Loading, empty, and error states verified manually or via E2E tests
- [ ] `npm run build` passes with no errors related to this module

---

## 4. Integration Completion

| Criterion | Requirement |
|---|---|
| **Upstream dependencies connected** | Module consumes real data/events from upstream modules — not mocks |
| **Downstream contracts honored** | Events and APIs exposed for downstream modules match documented contracts |
| **Event contracts** | Event payloads include tenant ID, entity ID, timestamps, and fields documented in architecture |
| **APIs documented** | New endpoints documented (route, method, auth, request/response schema, error codes) |
| **Backward compatibility** | API changes are additive; breaking changes require explicit versioning and migration plan |
| **Failure handling** | Cross-module failures degrade gracefully — partial outages do not corrupt data |

### Integration completion gate

- [ ] Dependency matrix in `module-dependency-map.md` satisfied for this module
- [ ] At least one integration test validates cross-module flow where applicable
- [ ] Event consumers (if any) are implemented or explicitly scheduled with ticket reference

---

## 5. Test Completion

| Test type | Requirement |
|---|---|
| **Unit tests** | Service-layer business rules, calculations (commission, tax, duration logic), and validators have unit tests |
| **Integration tests** | API feature tests cover happy path and primary error paths per endpoint; database interactions verified |
| **E2E tests** | Critical user workflows for the module have end-to-end tests (e.g., create booking → check-in → checkout for booking+POS integration) |
| **Permission tests** | Tests confirm authorized roles succeed and unauthorized roles are denied for each protected action |
| **Regression considerations** | Changes include tests that would catch reintroduction of fixed bugs; existing test suite still passes |

### Test completion gate

- [ ] `php artisan test` passes (backend)
- [ ] New tests added for new business logic — not zero-test modules
- [ ] Permission denial tested for at least one restricted action per module

---

## 6. Production Readiness

| Criterion | Requirement |
|---|---|
| **Observability hooks** | Errors logged with context (tenant, user, entity); key operations emit metrics or structured logs suitable for monitoring |
| **Error handling** | Unhandled exceptions do not expose internal details to end users; errors are caught at appropriate boundaries |
| **Documentation updated** | Module behavior, API changes, and configuration requirements reflected in relevant product/architecture docs |
| **No critical open defects** | No P0/P1 bugs remain open for module acceptance; known P2 issues documented with ticket references |
| **Performance baseline** | List endpoints paginated; N+1 queries avoided; heavy operations queued where appropriate |
| **Security review** | No secrets in code; input sanitized; SQL injection and XSS vectors addressed; file uploads validated if applicable |
| **Feature flags** | If module is flag-gated, flag defaults and plan enforcement documented; backend enforces flag — not UI-only |

### Production readiness gate

- [ ] No critical or high-severity defects open for this module
- [ ] Deployment/migration steps documented if non-standard
- [ ] Rollback strategy considered for schema migrations

---

## Module Acceptance Workflow

1. **Self-assess** against all six sections above
2. **Sub-capability audit** — walk scope-lock.md checklist line by line
3. **Test verification** — run full backend and frontend build/test suites
4. **Integration verification** — confirm upstream/downstream connections per dependency map
5. **Sign-off** — module marked complete only when all relevant gates pass

### Acceptance record template

```markdown
## Module [N] — [Name] Acceptance

**Date:** YYYY-MM-DD
**Assessor:** [name/role]

### Sub-capabilities (from scope-lock.md)
- [ ] [capability 1]
- [ ] [capability 2]
- ...

### DoD sections
- [ ] Functional completion
- [ ] Backend completion
- [ ] Frontend completion
- [ ] Integration completion
- [ ] Test completion
- [ ] Production readiness

### Open items
- [none / list with ticket IDs]

### Decision
- [ ] ACCEPTED — module complete
- [ ] REJECTED — gaps listed above
```

---

## Anti-Patterns — Never Mark Complete

The following are **automatic rejections** for module completion:

- UI exists but API returns mock/hardcoded data
- Backend exists but no permission checks
- Feature works for one tenant but tenant isolation is not enforced
- Sub-capabilities implemented as TODO comments
- Tests skipped with "will add later"
- Analytics or reporting built on seed data only
- Integration module with webhook stubs that never fire
- "Complete" label in PR description without DoD checklist

---

## Binding Statement

**A module cannot be marked complete unless all relevant DoD sections pass.**

"Relevant" means: every section applies unless the module has no frontend (not applicable to NeatMeet OS modules), no external integration surface (still applies at event contract level), or is explicitly documented as infrastructure-only.

When in doubt, the stricter interpretation applies.

---

*This Definition of Done is binding for all NeatMeet OS delivery — human and AI-assisted.*

# NeatMeet OS AI Delivery Rules

## Purpose

This document is the **operating rulebook** for Cursor and AI-assisted implementation of NeatMeet OS. It governs how AI agents must behave during future build steps to prevent scope drift, partial delivery, architecture inconsistency, and false completion claims.

**Every AI-assisted implementation session must read and follow these rules.**

---

## Before You Start Any Task

1. **Read the relevant product docs:**
   - `../01-product-vision/neatmeet-os-product-charter.md` — product context and goals
   - `../01-product-vision/scope-lock.md` — mandatory sub-capabilities
   - `module-dependency-map.md` — implementation order and dependencies
   - `implementation-roadmap.md` — delivery phases and commerce cluster
   - `definition-of-done.md` — acceptance criteria
   - `../../architecture/domain-boundaries.md` — what each domain owns
   - `../../architecture/system-architecture.md` — overall technical structure
   - `../../architecture/technology-decisions.md` — committed stack

2. **Identify the module and sub-capabilities** your task addresses. Reference them by module number and name from scope-lock.md.

3. **Verify dependencies** are complete (or document gaps explicitly) per the dependency map.

4. **List impacted files and tests** before major implementation work — in your plan or PR description.

---

## Scope Discipline

### Do not invent scope

- **Do not invent scope** outside approved module contracts defined in scope-lock.md.
- **Do not add features** not traced to a sub-capability or explicitly labeled **extension scope**.
- **Do not remove or defer** sub-capabilities to simplify implementation without reporting the gap.
- **Do not silently skip** sub-capabilities listed in the roadmap — even if they seem hard or tedious.

### Do not claim false completion

- **Do not claim completion** if acceptance criteria in definition-of-done.md are unmet.
- **Do not replace missing implementation with TODOs** and call the module complete.
- **Do not mark UI-only work as done** when backend enforcement, permissions, or tenant isolation are missing.
- **Do not use mock data or stub services** in production code paths and present the feature as functional.

### Surface assumptions

- **If architecture assumptions are needed**, state them explicitly before implementing.
- **If requirements are ambiguous**, document your interpretation and the alternatives considered.
- **If a dependency is missing**, stop and report it — do not guess around it with local mocks.
- **If a scope change seems necessary**, propose it as a documented change to scope-lock.md — do not silently redefine scope.

---

## Architecture Discipline

### Extend, don't replace

- **Reuse existing services, controllers, patterns, and UI components** — do not create parallel implementations.
- **Follow established module structure** (Controller → Service → Repository).
- **Use events for cross-module communication** — do not reach across domain boundaries with direct database access to another module's tables.

### Do not rewrite foundations without impact analysis

- **Do not rewrite foundational patterns** (tenancy middleware, RBAC, audit logging, API client, design system) without surfacing the impact on all dependent modules.
- **Do not change domain boundaries** without updating `domain-boundaries.md`.
- **Do not break additive API contracts** — API changes must be backward-compatible unless explicitly versioned.

### First-class concerns — never optional

These are **non-negotiable** in every module:

| Concern | Rule |
|---|---|
| **Tenant isolation** | Every query scopes by tenant; never return cross-tenant data |
| **Permissions** | Every mutation and sensitive read checks authorization |
| **Auditability** | Consequential changes write audit log entries |
| **Backend enforcement** | Business rules enforced in services — not UI-only |

---

## Implementation Behavior

### Production work only

- Implement **complete, production-ready** functionality — not scaffolds, placeholders, or "we'll wire this later" shells.
- **Migrations, services, validations, tests** are part of the task — not follow-up items.
- **No "TBD" sections** in code or docs where a concrete rule or implementation can be written now.

### Match existing conventions

- Read surrounding code before writing; match naming, structure, and patterns.
- **Minimize scope** — only change what the task requires.
- **Do not over-engineer** — no unnecessary abstractions or premature optimization.

### Testing is part of delivery

- Add **PHPUnit feature tests** for backend endpoints and business rules.
- Add tests for **permission denial** and **tenant isolation** where applicable.
- Ensure **`php artisan test`** and **`npm run build`** pass before considering work done.

---

## Reporting and Transparency

### When to stop and report

Stop implementation and report to the user when:

1. A **required upstream module** is incomplete and blocks correct implementation
2. A **scope ambiguity** cannot be resolved from existing docs
3. A **domain boundary conflict** exists (two modules claim ownership of the same entity)
4. Implementation would require a **breaking API or schema change** affecting other modules
5. The task as stated would **violate scope-lock** or these delivery rules

### What to include in reports

- Module and sub-capability affected
- Specific blocker or ambiguity
- Recommended resolution options
- Impact if proceeding without resolution

### PR and commit discipline

- Reference module number and sub-capabilities in PR descriptions
- Include DoD checklist self-assessment for module-level work
- List impacted files and new tests
- Do not commit unless explicitly asked by the user

---

## Dependency and Ordering Rules

1. **Respect module-dependency-map.md** — do not build downstream modules on incomplete foundations.
2. **Connect to real upstream data** — if booking exists, POS must use real appointments, not seeded fakes.
3. **Emit domain events** at lifecycle points defined in domain-boundaries.md.
4. **Integrations (Module 13) come last** — do not build external connectors before internal contracts are stable.

---

## Documentation Discipline

- **Update relevant docs** when behavior, boundaries, or contracts change.
- **Do not create new documentation files** unless the task requires it or the user asks.
- **Keep scope-lock.md authoritative** — if it conflicts with other docs, scope-lock wins.

---

## Quick Reference Checklist

Before marking any task complete, verify:

- [ ] Task maps to scope-lock.md sub-capability(ies)
- [ ] Dependencies satisfied per module-dependency-map.md
- [ ] Tenant isolation enforced on all new queries
- [ ] Permissions enforced on all new endpoints/actions
- [ ] Audit logging on consequential mutations
- [ ] No mock/fake dependencies in production paths
- [ ] No TODOs substituting for real implementation
- [ ] Tests added and passing
- [ ] Frontend: loading, empty, error states handled
- [ ] `php artisan test` passes
- [ ] `npm run build` passes
- [ ] DoD self-assessment honest — no false completion

---

## Enforcement

Violations of these rules — especially false completion claims, silent scope reduction, and missing tenant isolation — must be corrected before the module or task is accepted.

AI agents that encounter prior violations should **report them** rather than compound them with additional workarounds.

---

*These rules are binding for all NeatMeet OS AI-assisted delivery in Cursor.*

# NeatMeet OS — Observability & Operations

## Purpose

This document defines the operational baseline for **NeatMeet OS** production readiness: logging, monitoring, environments, backups, and CI/CD expectations.

---

## A. Logging Strategy

### Application logs

| Log type | Format | Destination |
|---|---|---|
| **HTTP request logs** | Structured JSON | `stdout` → log aggregator |
| **Domain service logs** | Structured JSON with context | `stdout` |
| **Queue job logs** | JSON with `job_id`, `queue`, `attempt` | `stdout` + Horizon |
| **Integration logs** | JSON with `provider`, `sync_job_id` | `stdout` + DB `sync_jobs` |

### Structured log fields (mandatory)

```json
{
  "timestamp": "ISO8601",
  "level": "info|warning|error",
  "message": "human readable",
  "correlation_id": "uuid",
  "tenant_id": "uuid|null",
  "user_id": "uuid|null",
  "domain": "booking|pos|...",
  "context": {}
}
```

### Audit logs vs application logs

| Type | Storage | Purpose |
|---|---|---|
| **Audit logs** | PostgreSQL `audit_logs` | Compliance, who changed what |
| **Application logs** | Files/stdout → aggregator | Debugging, ops |

Audit logs are queryable in admin UI. Application logs are for engineers.

### Correlation IDs

- `X-Correlation-ID` header generated at Nginx or Laravel middleware
- Propagated to queue jobs, integration calls, and WebSocket messages
- Returned in API error responses for support tickets

---

## B. Error Monitoring & Alerting

### Exception tracking

**Sentry** (or equivalent) for:

- Unhandled exceptions in API and workers
- Frontend errors (Next.js Sentry SDK)
- Breadcrumb context: tenant, user, correlation ID

### Alert tiers

| Severity | Examples | Response |
|---|---|---|
| **P0 — Critical** | Payment capture down, DB unreachable, auth broken | Immediate page |
| **P1 — High** | Queue backlog > 1000, integration sync failing > 1hr | Same-day fix |
| **P2 — Medium** | Elevated 5xx rate, slow queries | Next business day |
| **P3 — Low** | Single tenant import warning | Backlog |

### Job failure monitoring

- Laravel Horizon dashboard for failed jobs
- Alert when failed job count exceeds threshold in 15 minutes
- Payment and webhook jobs: dedicated `critical` queue with immediate alert

### Integration failure monitoring

- `sync_jobs.status = failed` → admin notification + Sentry
- Payment provider webhook failures logged with payload hash (not full PCI data)

### Payment failure monitoring

- `PaymentFailed` events aggregated per tenant per hour
- Alert if failure rate > 10% for any tenant (possible misconfiguration)

---

## C. Metrics & Health Monitoring

### Health endpoints

| Endpoint | Checks |
|---|---|
| `GET /health` | App alive |
| `GET /health/ready` | DB, Redis, queue connectivity |
| `GET /health/deep` | S3 write test (staging/prod admin only) |

### Application metrics (Prometheus-compatible optional)

| Metric | Type | Use |
|---|---|---|
| `http_request_duration_seconds` | Histogram | API latency |
| `http_requests_total` | Counter | Traffic by route/status |
| `queue_jobs_processed_total` | Counter | Worker throughput |
| `queue_jobs_failed_total` | Counter | Job health |
| `payments_captured_total` | Counter | Revenue ops |
| `payments_failed_total` | Counter | Payment health |
| `bookings_created_total` | Counter | Product usage |
| `active_tenants_gauge` | Gauge | SaaS health |

### Business metrics (Analytics module)

Tracked via `analytics_events`, not Prometheus:

- Booking conversion rate
- No-show rate
- Average ticket value
- Client retention

### Tenant-level signals (admin)

Platform admin dashboard:

- Tenants with failed payments spike
- Tenants with sync backlog
- Tenants approaching plan limits

---

## D. Backup / Recovery / Resilience

### Database backups

| Policy | Detail |
|---|---|
| **Frequency** | Daily full backup + WAL archiving (continuous) |
| **Retention** | 30 days daily; 12 monthly |
| **Storage** | Encrypted S3 bucket, separate region |
| **Test restore** | Monthly restore drill to staging |

### File storage backups

- S3 versioning enabled
- Cross-region replication (production)

### Recovery objectives

| Target | Goal |
|---|---|
| **RPO** (max data loss) | < 1 hour |
| **RTO** (max downtime) | < 4 hours |

### Resilience patterns

- Queue retries with exponential backoff
- Database transactions for commerce operations (order + payment + stock)
- Graceful degradation: if marketing provider down, queue messages — don't block booking
- Circuit breaker on integration adapters after N consecutive failures

---

## E. Environment Strategy

| Environment | Purpose | Data |
|---|---|---|
| **Local** | Developer machines | Docker Compose: Postgres, Redis, MinIO, Mailpit |
| **Test** | CI automated tests | Ephemeral DB per run |
| **Staging** | Pre-production validation | Anonymized copy or seed data |
| **Production** | Live tenants | Real data, strict access |

### Environment parity

- Staging mirrors production topology (Nginx, PHP-FPM, PM2, Horizon, Reverb)
- Same Laravel env keys; different secrets
- Feature flags may be ahead in staging

### Secrets management

- `.env` on server (not in git)
- Production secrets rotated quarterly
- Payment provider keys per-tenant in encrypted `integration_configs.credentials`

---

## F. CI/CD Expectations

### Pipeline stages (GitHub Actions)

```
Push / PR
  ├── backend: composer install → pint → php artisan test
  ├── frontend: npm ci → lint → typecheck → vitest → npm run build
  └── (main only) deploy to staging

Tag / manual approve
  └── deploy to production
```

### Deploy steps (production)

1. Maintenance mode (optional, brief)
2. `git pull` + verify commit
3. `composer install --no-dev`
4. `php artisan migrate --force`
5. `php artisan config:cache && route:cache`
6. `npm run build` (frontend)
7. `pm2 restart` (frontend, reverb, horizon)
8. `php artisan queue:restart`
9. Smoke test health endpoints
10. Maintenance mode off

### Deployment rules

- Migrations must be backward-compatible (expand-contract pattern)
- No destructive migrations without explicit maintenance window
- Rollback: previous git tag + reverse migration if safe

### Quality gates (mandatory before merge)

- `php artisan test` passes
- `npm run build` passes
- No new critical Sentry issues in staging soak

---

## Related Documents

| Document | Purpose |
|---|---|
| `technology-decisions.md` | Stack and tooling |
| `system-architecture.md` | Deployment topology |
| `../product/04-delivery/definition-of-done.md` | Production readiness criteria |

---

*NeatMeet OS observability and operations — Step 2 blueprint.*

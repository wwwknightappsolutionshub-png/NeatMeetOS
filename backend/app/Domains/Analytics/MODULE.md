# Module 12A ù Analytics & Reporting Foundation

Read-only operational analytics domain. Aggregates KPIs across CRM, Booking, Payments, POS, Memberships, Inventory, Marketing, and Notifications without taking ownership of their records.

## Scope (12A)

- Tenant-scoped reporting services over existing operational tables (no materialized rollup tables)
- Admin API under `/api/v1/admin/analytics/*`
- Date-range filtering with optional `location_id` / `provider_id` where applicable
- Permissions: `analytics.view`, `analytics.reporting.view`

## Endpoints

| Method | Path | Filters | Description |
|--------|------|---------|-------------|
| GET | `/analytics/overview` | `from`, `to`, `location_id`, `provider_id` | Cross-domain dashboard summary |
| GET | `/analytics/bookings` | `from`, `to`, `location_id`, `provider_id` | Booking status counts, daily series, provider/service breakdown |
| GET | `/analytics/revenue` | `from`, `to`, `location_id`, `provider_id` | Payments + deposits + POS revenue summary |
| GET | `/analytics/clients` | `from`, `to`, `location_id` | Client growth, tags, consent uptake, membership attachment |
| GET | `/analytics/inventory` | `from`, `to`, `location_id` | Low stock snapshot, movement breakdown, top consumed items |
| GET | `/analytics/communications` | `from`, `to` | Marketing + Notifications operational delivery summary |

All routes require `analytics.view`. Owner and Manager roles receive both `analytics.view` and `analytics.reporting.view` (reserved for future granular reporting surfaces in 21B).

Default date window when `from`/`to` omitted: **last 30 days** (inclusive).

## Module 12B ù Saved reports & exports

Additive extension (backend Step 22A). Lets tenants save analytics report presets and run synchronous CSV/JSON exports of the 12A datasets.

### Tables

| Table | Purpose |
|-------|---------|
| `analytics_saved_reports` | Reusable report definitions (type, filters JSON, format, scheduling metadata placeholders, archived flag) |
| `analytics_export_jobs` | Export runs with snapshotted type/filters, status, file metadata (`file_disk`/`file_path`/`file_name`), row count and lifecycle timestamps |

### Endpoints (require `analytics.exports.manage`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/analytics/saved-reports` | List saved reports (`report_type`, `archived` filters; excludes archived by default) |
| POST | `/analytics/saved-reports` | Create saved report |
| GET | `/analytics/saved-reports/{id}` | Show saved report |
| PUT | `/analytics/saved-reports/{id}` | Update saved report |
| PATCH | `/analytics/saved-reports/{id}/archive` | Archive saved report |
| POST | `/analytics/saved-reports/{id}/run` | Create + execute an export from a preset |
| GET | `/analytics/exports` | List export jobs (`report_type`, `status` filters) |
| POST | `/analytics/exports` | Create + execute an ad-hoc export |
| GET | `/analytics/exports/{id}` | Show export job |
| GET | `/analytics/exports/{id}/download` | Download the generated file (completed jobs only) |

### Export behaviour

- **Synchronous execution** ù exports run inline in the request/service flow. No queues, workers, cron, or emailed delivery in 12B. Jobs still pass through `pending` ? `processing` ? `completed`/`failed` within the same request for a consistent audit trail.
- **Formats** ù `csv` (compact primary row set per report type) and `json` (full structured payload wrapped with `report_type`, `generated_at`, `filters`, `range`, `data`). PDF/XLSX are out of scope.
- **CSV primary row sets** ù overview: one KPI summary row; bookings/revenue/clients: daily series; inventory: low-stock items; communications: per-channel breakdown rows (may be empty when no messages exist).
- **Storage** ù files are written to the `local` disk under `analytics/exports/{tenantId}/analytics-{type}-{Y-m-d-His}.{ext}`.
- **Download** ù `GET /exports/{id}/download` requires `analytics.exports.manage`, tenant ownership, `completed` status, and an on-disk file. Returns 404 when the job is incomplete, cross-tenant, or the file is missing. The admin UI downloads via authenticated blob fetch (not a bare browser link).
- **Scheduling** ù `is_scheduled` + `schedule_*` fields are persisted as configuration only; no background delivery is wired yet.
- **Archived presets** cannot be run (422) and are excluded from the default saved-reports list.

### Permissions

- `analytics.exports.manage` ù manage saved reports, run exports, download files. Granted to Owner and Manager.

### Audit events

`analytics_saved_report.created` / `.updated` / `.archived`, `analytics_export.created` / `.completed` / `.failed`.

### Services

| Service | Responsibility |
|---------|----------------|
| `AnalyticsSavedReportService` | Saved report CRUD + archive, filter/schedule validation, filters JSON normalisation |
| `AnalyticsExportService` | Create + synchronously execute export jobs, file generation, status/metadata persistence |
| `AnalyticsExportTransformer` | Flatten 12A payloads into CSV header + rows per report type |

## Timestamp semantics

| Domain | Metric | Timestamp column |
|--------|--------|------------------|
| Booking | Appointment activity / status counts | `appointments.starts_at` |
| Booking | Service revenue snapshots | `appointments.starts_at` (via join) |
| Payments | Collected / failed counts | `payment_transactions.created_at` |
| Payments | Refund totals | `payment_refunds.created_at` |
| Deposits | Collected | `commerce_deposit_records.collected_at` |
| Deposits | Refunded | `commerce_deposit_records.refunded_at` |
| POS | Completed sales | `commerce_checkouts.completed_at` |
| CRM | New clients | `clients.created_at` |
| CRM | Consent uptake | Latest `client_consent_records.recorded_at` per `(client, consent_type)` |
| Inventory | Movements | `inventory_movements.created_at` |
| Inventory | Low stock | Point-in-time snapshot (`inventory_levels.on_hand_quantity` vs `reorder_point`) |
| Memberships | Active counts | Current `status` column (not date-filtered) |
| Wallet / loyalty liability | Ledger derivation | Non-expired entries only (`expires_at IS NULL OR expires_at > now()`) |
| Marketing | Messages / campaigns / executions | `created_at` on respective tables |
| Notifications | Messages | `notifications_messages.created_at` |

## Services

| Service | Responsibility |
|---------|----------------|
| `AnalyticsDateRangeResolver` | Normalises `from`/`to` query params |
| `AnalyticsScopeValidator` | Resolves active tenant id |
| `AnalyticsOverviewService` | Cross-domain dashboard payload |
| `BookingAnalyticsService` | Booking KPIs, daily, providers, services |
| `RevenueAnalyticsService` | Payments, deposits, POS revenue |
| `ClientAnalyticsService` | Client growth, tags, consents |
| `InventoryAnalyticsService` | Stock movements, low stock, consumption |
| `CommunicationsAnalyticsService` | Marketing + Notifications delivery |
| `MembershipMetricsService` | Active memberships/packages, wallet/loyalty liability |

## Domain boundary rules

- Analytics is **read-only** ù no mutations, no audit events on reads
- Queries existing domain tables via Eloquent query builder; does not duplicate ownership
- Marketing vs Notifications delivery metrics remain separate sections

## Intentionally omitted metrics

| Metric | Reason |
|--------|--------|
| `preferred_channel` summary on overview | No dedicated scalar column; stored in JSON `clients.preferences` without stable schema |
| Finance-grade net revenue / accounting | Out of scope ù operational reporting only |
| Real-time provider delivery analytics | Simulation-first dispatch; counts based on message status fields |

## Deferred (beyond 12B)

- Background queued exports / async workers
- Scheduled emailed report delivery (cron)
- PDF/XLSX export formats
- Custom report builder / arbitrary SQL
- Chart image generation
- External BI connectors / webhooks
- Warehouse / OLAP / snapshot tables
- Cohort analysis / forecasting / advanced attribution
- Finance-grade accounting reports
- Payroll / commission reporting

## Tests

| File | Coverage |
|------|----------|
| `tests/Feature/Module12AAnalyticsAdminTest.php` | 9 HTTP tests ó read-only analytics endpoints, permission gates, tenant isolation |
| `tests/Feature/Module12BAnalyticsExportsAdminTest.php` | 20 HTTP tests - saved reports CRUD/archive, exports, download, failure path, tenant isolation |
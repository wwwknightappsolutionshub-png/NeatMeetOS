# NeatMeet OS — Integration Architecture

## Purpose

This document defines the integration model for **NeatMeet OS** — how external systems connect, how failures are handled, and how migration/import tooling works at a high level.

---

## A. Integration Categories

| Category | Examples | Priority |
|---|---|---|
| **Payments** | Stripe, PayPal, Square, regional gateways | Commerce backbone |
| **Accounting** | Xero, QuickBooks, Sage | Post-POS baseline |
| **Calendar sync** | Google Calendar, Outlook (staff calendars) | Booking stable |
| **Google Business / Reserve with Google** | Appointment booking from Google | Booking + location stable |
| **Ecommerce connectors** | Shopify sync (catalog/orders) | Ecommerce module |
| **Marketing connectors** | Mailchimp, Twilio, WhatsApp Business API | Marketing module |
| **BI exports** | CSV, S3 parquet, BigQuery sink | Analytics module |
| **Webhooks / public API** | Tenant-configured outbound webhooks; REST API keys | Integrations capstone |
| **Migration / import** | CSV, legacy salon software exports | Onboarding |

---

## B. Integration Architecture Principles

### Provider adapter pattern

Each external category defines an interface in `Domains/Integrations/Contracts/`:

```php
interface PaymentProviderInterface {
    public function createPaymentIntent(DepositRequest $request): PaymentIntentResult;
    public function capture(string $externalId): CaptureResult;
    public function refund(string $externalId, Money $amount): RefundResult;
}
```

Concrete adapters: `StripePaymentProvider`, `XeroAccountingProvider`, etc.

### Anti-corruption layer (ACL)

- External payloads never leak into domain services raw
- ACL translates provider JSON → internal DTOs
- Internal events never expose provider-specific IDs to other domains (use `external_id_maps`)

### Retry and idempotency

| Pattern | Application |
|---|---|
| **Idempotency keys** | Payment captures, webhook processing, refund requests |
| **Exponential backoff** | Sync jobs (3 retries, then dead letter) |
| **Webhook deduplication** | Store `provider_event_id` unique; ignore duplicates |
| **Outbox pattern** | Critical events written to `integration_outbox` before external call |

### Sync jobs vs webhooks vs manual import

| Mode | When |
|---|---|
| **Webhooks (inbound)** | Payments, ecommerce order updates — real-time |
| **Scheduled sync jobs** | Accounting daily export, catalog sync — batch |
| **On-demand API pull** | Calendar two-way sync — triggered + periodic |
| **Manual import** | Migration onboarding — admin-uploaded CSV |

### Failure handling

1. Job fails → retry with backoff
2. Retries exhausted → `sync_jobs.status = failed`, alert via Sentry + admin notification
3. Partial sync → transactional per-record; failed records logged in `sync_job_errors`
4. Payment failure → never silent; `PaymentFailed` event → admin + client notification

### Auditability of external syncs

Every sync records:

`integration_config_id`, `direction` (inbound/outbound), `entity_type`, `entity_id`, `external_id`, `status`, `request_summary`, `response_summary`, `synced_at`

---

## C. Public API Strategy

### Primary API: REST JSON

**Chosen:** REST as primary external API.

| Aspect | Decision |
|---|---|
| Format | JSON over HTTPS |
| Versioning | URL prefix `/api/v1/`; breaking changes → `/api/v2/` |
| Auth | API keys (tenant-scoped) + OAuth2 (extension for marketplace partners) |
| Rate limiting | Per-key: 60 req/min default; configurable per plan |
| Pagination | Cursor-based for lists |
| Errors | RFC 7807-style problem+json |

### GraphQL

**Not primary.** May add read-only GraphQL for analytics partners as extension scope.

### Webhooks (outbound)

Tenants configure webhook endpoints for events:

- `appointment.confirmed`, `order.paid`, `client.created`, etc.
- HMAC signature header for verification
- Retry: 5 attempts over 24 hours
- Delivery log visible in admin UI

### API documentation

- OpenAPI 3.1 spec generated from Laravel routes + schemas
- Published at `/api/docs` (authenticated admin)
- SDK generation optional (extension)

---

## D. Migration / Import Architecture

### Import sources

| Source | Approach |
|---|---|
| **CSV upload** | Admin maps columns → internal fields via mapping UI |
| **Legacy salon software** | Pre-built importers per platform (Phorest, Fresha, etc.) as adapters |
| **API migration** | Pull from old system API if available |

### Import job flow

```
Upload file → Validate schema → Preview (dry run) → Map fields
     → ImportJob queued → Chunk processing (500 rows/batch)
     → external_id_maps written → Validation report → Admin review
```

### Data imported (typical onboarding)

1. Locations and workspaces
2. Staff / team members
3. Service catalog
4. Clients (PII consent flagged)
5. Future appointments (optional)
6. Product catalog (optional)

### Import principles

- **Idempotent** — Re-import matches on `external_id_map`, updates not duplicates
- **Tenant-scoped** — Import never crosses tenants
- **Non-destructive default** — Imports add/update; delete requires explicit flag
- **Audit trail** — Full import job log with error rows downloadable

### Migration assistant (product)

Guided wizard in admin:

1. Select source system
2. Upload export / connect API
3. Map fields with smart defaults
4. Dry run preview
5. Execute + reconciliation report

---

## Integration Topology

```
┌─────────────────────────────────────────────────────────────┐
│                  NeatMeet OS (Laravel)                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ Domain      │  │ Integration │  │ Webhook Controller  │ │
│  │ Services    │→ │ Adapters    │← │ (inbound)           │ │
│  └─────────────┘  └──────┬──────┘  └─────────────────────┘ │
└─────────────────────────┼───────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
   ┌─────────┐      ┌──────────┐      ┌──────────┐
   │ Stripe  │      │   Xero   │      │  Google  │
   │ Payments│      │Accounting│      │ Calendar │
   └─────────┘      └──────────┘      └──────────┘
```

---

## Related Documents

| Document | Purpose |
|---|---|
| `data-architecture.md` | `external_id_maps`, event catalog |
| `technology-decisions.md` | Stack choices |
| `observability-and-ops.md` | Sync failure alerting |

---

*NeatMeet OS integration architecture — Step 2 blueprint.*

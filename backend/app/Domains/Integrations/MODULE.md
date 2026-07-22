# Module 13A + 13B — Provider Integrations Foundation & Live Adapters

Shared provider layer for external delivery/capture systems. Step 23B adds the admin UI; Step 24 adds live adapter foundations.

## Ownership model

| Layer | Owns |
|-------|------|
| Notifications | `notifications_messages`, preferences, templates, `notifications_message_attempts` |
| Marketing | campaigns, workflows, executions, `marketing_messages`, `marketing_message_attempts` |
| Payments | `payment_transactions`, refunds, allocations |
| **Integrations** | `provider_accounts`, `provider_delivery_attempts`, `provider_webhook_events` |

Domain records remain authoritative. Provider attempts are **dual-logged** alongside domain-specific attempt tables when dispatch occurs.

## Simulation-first behaviour (preserved)

- Domain dispatch (Notifications / Marketing / Payments) still simulates locally for domain status transitions.
- Each dispatch also creates a `provider_delivery_attempts` row.
- When no active default account exists, credentials are invalid, or driver is simulation/manual, dispatch uses **implicit simulation fallback** (`metadata.simulation_fallback = true`).
- Archiving or deactivating a default account clears `is_default` and prevents routing to that account.

## Module 13B — Live adapter foundation

### Adapter layer

| Driver | Category | Adapter | Transport |
|--------|----------|---------|-----------|
| `mailgun` | `email` | `MailgunEmailAdapter` | Live HTTP Messages API |
| `twilio` | `sms` | `TwilioSmsAdapter` | Live HTTP Messages API |
| `stripe` | `payment_gateway` | `StripePaymentLinkAdapter` | Stub |

- Contract: `ProviderOutboundAdapterContract`
- Registry: `ProviderAdapterRegistry`
- Dispatch: `ProviderDispatchService` routes live drivers through adapters when credentials validate; otherwise falls back to simulation.
- Webhooks: `ProviderWebhookSignatureVerifier` validates Stripe/Mailgun/Twilio HMAC when secrets are configured.
- Category/driver mismatches (e.g. Stripe + email) are rejected at account create/update.

### Credentials

Stored encrypted on `provider_accounts.credentials_json`. API exposes `has_credentials` and safe `config_summary` only — never raw secrets.

Test endpoint results:
- `simulation_ok` — simulation/manual drivers
- `credentials_valid_stub` — live driver, required fields present
- `credentials_missing` — live driver, incomplete credentials
- `category_driver_mismatch` — invalid combo

### Attempt metadata (live-capable)

Live adapter attempts may include:
- `driver`, `simulated`, `transport` (`stub`)
- `provider_reference`, `remote_status`
- `simulation_fallback`, `live_fallback_reason`, `missing_credential_fields` when fallback occurs

### Webhook ingest

Public `POST /api/v1/integrations/webhooks/{driver}` stores append-only events with driver-specific normalization in `metadata.normalized`. **Signature validation is deferred** (`signature_valid = null`, `metadata.signature_check = deferred`).

Normalizers: Stripe, Mailgun, Twilio.

### Retry

Simulation-only (same as 13A). Live-driver failed attempts return 422 on retry.

## Permissions

| Permission | Owner | Manager |
|------------|-------|---------|
| `integrations.view` | yes | yes |
| `integrations.manage` | yes | no |
| `integrations.reporting.view` | yes | yes |

## Admin API routes

Base: `/api/v1/admin/integrations`

| Method | Path | Permission |
|--------|------|------------|
| GET | `/provider-accounts` | `integrations.view` |
| POST | `/provider-accounts` | `integrations.manage` |
| GET | `/provider-accounts/{id}` | `integrations.view` |
| PUT | `/provider-accounts/{id}` | `integrations.manage` |
| PATCH | `/provider-accounts/{id}/activate` | `integrations.manage` |
| PATCH | `/provider-accounts/{id}/deactivate` | `integrations.manage` |
| PATCH | `/provider-accounts/{id}/archive` | `integrations.manage` |
| PATCH | `/provider-accounts/{id}/set-default` | `integrations.manage` |
| POST | `/provider-accounts/{id}/test` | `integrations.manage` |
| GET | `/provider-attempts` | `integrations.view` |
| GET | `/provider-attempts/{id}` | `integrations.view` |
| POST | `/provider-attempts/{id}/retry` | `integrations.manage` |
| GET | `/provider-events` | `integrations.view` |
| GET | `/provider-events/{id}` | `integrations.view` |

Public webhook intake (no auth):

| Method | Path |
|--------|------|
| POST | `/api/v1/integrations/webhooks/{driver}` |

When `provider_account_id` is supplied, the event tenant is bound to that account's tenant. A mismatched `tenant_id` in the request body is rejected with 422. Signature validation remains deferred.

## Admin UI

| Route | Purpose |
|-------|---------|
| `/admin/integrations` | Overview + module banner |
| `/admin/integrations/provider-accounts` | List/create with live driver credential forms |
| `/admin/integrations/provider-accounts/[accountId]` | Detail, test, missing-credential warnings |
| `/admin/integrations/provider-attempts` | Ledger with live/simulation transport badges |
| `/admin/integrations/provider-attempts/[attemptId]` | Detail + simulation-only retry |
| `/admin/integrations/provider-events` | Webhook list |
| `/admin/integrations/provider-events/[eventId]` | Payload/headers/normalized metadata |

## Domain bridges

- **Notifications** — `NotificationDispatchSimulationService` ? `ProviderDispatchBridge::recordNotificationDispatch()`
- **Marketing** — `MarketingDispatchSimulationService` ? `ProviderDispatchBridge::recordMarketingDispatch()`
- **Payments** — `PaymentTransactionService::createPaymentLink()` ? `PaymentProviderAttemptContract`

## Audit events

`provider_account.created`, `.updated`, `.activated`, `.deactivated`, `.archived`, `.tested`  
`provider_attempt.created`, `.sent`, `.failed`  
`provider_webhook.received`, `.processed`, `.failed`

## Deferred beyond 13B

- Production Stripe / Twilio / Mailgun SDK HTTP transport
- Webhook signature validation (HMAC per provider)
- Automatic webhook reconciliation into business state
- Queue workers for async dispatch
- Live provider retry
- OAuth / marketplace install flows

## Tests

- Backend 13A: `tests/Feature/Module13AIntegrationsAdminTest.php`
- Backend 13B: `tests/Feature/Module13BIntegrationsLiveAdaptersTest.php`
- Closure: `tests/Feature/Module25PlatformClosureTest.php`
- Frontend: `frontend/lib/integrations-types.test.ts`

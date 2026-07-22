# Marketing domain

## Scope (Module 10A)

Marketing owns campaign definitions, automation rules, audience/segment definitions, message templates, message jobs, dispatch attempts, and marketing reporting summaries. It orchestrates recipient resolution and template rendering but does **not** own external transport � that belongs to Integrations (Module 13).

### In scope for 10A

- Campaign and automation management (email, SMS, WhatsApp, push, in-app channels)
- Trigger types: booking reminder, rebooking nudge, win-back, review request; placeholder-ready: birthday, membership renewal
- Campaign lifecycle: draft ? active ? paused ? archived
- Tenant-scoped audiences (tags, location, active/inactive, consent, preferred team member, loyalty display, future booking, last-visit filters where derivable)
- Reusable message templates with variable placeholders and preview rendering
- Automation execution queue (`marketing_runs`, `marketing_messages`, `marketing_message_attempts`)
- Consent-aware send eligibility via CRM consent history
- Admin-triggered automation generation (reminders, rebook, review, win-back) and broadcast preview/dispatch
- **Simulated dispatch** � records delivery intent and attempt history with `provider = simulation`
- Basic reporting (campaign totals, run summaries, sent/failed/skipped counts)

### Ownership boundaries

| Domain | Owns | Marketing may� |
|--------|------|----------------|
| **Marketing** | Campaigns, templates, audiences, runs, messages, reporting | � |
| **CRM** | Client profile, tags, consent history | Read client/contact/consent; never duplicate consent state except message snapshots |
| **Booking** | Appointments, booking events | Read appointment facts for automation inputs; **no writes** to booking tables in 10A |
| **Memberships** | Plans, wallet, loyalty ledgers | Read membership state for template variables only |
| **Integrations** | Provider adapters (Mailgun, Twilio, etc.) | Not implemented in 10A; `MarketingTransportProvider` contract is the plug-in point |

### Trigger rules (v1)

- **Booking reminder:** appointment status `pending` or `confirmed` (or `checked_in`); start time within `booking_reminder_hours_before`; client channel-eligible
- **Rebooking nudge:** appointment `completed`; completed/ended long enough ago per `rebooking_window_days`; no future booking
- **Review request:** appointment `completed` in date window; `review_request_enabled`; delay per `review_request_delay_hours`
- **Win-back:** client had completed appointments; last visit older than `win_back_inactivity_days`; no future booking

### Audit events

Marketing mutations write to `audit_logs`: `marketing_template.{created,updated,archived}`, `marketing_audience.{created,updated,archived}`, `marketing_campaign.{created,updated,status_updated}`, `marketing_run.{created,dispatched}`, `marketing_message.{sent,failed}`, and `marketing_automation_settings.{created,updated}`. `marketing_run.created` is emitted via the `MarketingRun` model `created` event so every generation/broadcast path is covered uniformly.

### Dispatch simulation

`MarketingDispatchSimulationService` marks messages `processing` ? creates `marketing_message_attempts` ? `sent` or `failed`. Provider metadata uses `provider = simulation`. Invalid/missing recipient addresses may deterministically fail.

### Deferred (not 10A)

- Real Mailgun / SendGrid / Twilio / WhatsApp delivery and webhooks
- Unsubscribe / public preference center
- Visual email builder, A/B testing, drip/branching workflows
- Campaign attribution beyond run summaries

### Extension: Branded email layout

- **`MarketingEmailLayoutService`** wraps email `body_html` at render/preview/send with tenant `primary_color`, logo, brand name, Anek Latin + Arial fallbacks, salon signature, and **Powered by NeatMeet OS**.
- Stored templates / TipTap edit **inner content only**; chrome is not baked into seed HTML.

### Extension: Scheduled cadences (v1)

- Artisan `marketing:run-scheduled` (every 5 minutes via `routes/console.php`): dispatch due messages, process workflow queue, win-back (inactive, 14-day cooldown, email+in_app), birthday (DOB today), membership reminder (last week of month, active memberships), monthly book nudge (week 1, distinct from visit-based rebooking).
- Welcome: email ~15s after client create (`DispatchClientWelcomeMarketingJob`); in-app once on first member PWA login.
- Join capture accepts `date_of_birth` (and maps birthday special-event month/day when labelled birthday).

### Extension: Push + In-app channels

- **`push`** � Web Push via CRM `MemberPushDispatchService` / `member_push_subscriptions`. Eligibility requires an active subscription and marketing email consent. Dispatch is native (not Integrations bridge); VAPID-missing environments simulate send.
- **`in_app`** � Member portal inbox via CRM `client_notices` + `ClientNoticeService`. Eligibility: active client (no external marketing consent). Member APIs: `GET /member/notices`, `POST /member/notices/{id}/read`.

See `docs/local-development.md` for routes, permissions, and demo flows.

## Scope (Module 10B)

Module 10B adds the **operational automation layer** on top of 10A: workflow journeys, per-client executions, suppressions, richer delivery states, and domain trigger integrations.

### In scope for 10B

- **Workflow definitions** (`marketing_automation_workflows`, `marketing_workflow_steps`) with triggers: `client_created`, `consent_granted`, `consent_withdrawn`, `appointment_completed`, `appointment_no_show`, `birthday`, `membership_started`, `membership_cancelled`, `manual`
- **Workflow executions** (`marketing_workflow_executions`, `marketing_workflow_execution_steps`) with statuses queued/running/completed/cancelled/failed/skipped
- **Contact suppressions** (`marketing_contact_suppressions`) � unsubscribe, hard bounce, manual, complaint, invalid contact
- **Richer message delivery** � delivered/opened/clicked/unsubscribed/suppressed timestamps and failure categories on `marketing_messages`
- **MarketingDeliveryService** � simulated dispatch + admin operational actions (mark delivered/opened/clicked/failed/unsubscribe)
- **MarketingAutomationTriggerService** � fires workflows from CRM/Booking/Memberships domain events (synchronous, wrapped in try/catch so core domains are never blocked). CRM consent changes (`consent_granted` / `consent_withdrawn`) fire via lazy resolution in `ClientConsentService` to avoid DI cycles.
- **MarketingWorkflowStepService** � granular step add/update/reorder/archive (bulk `PUT /steps` sync also supported)`n- **Admin APIs** � workflows (incl. nested executions list, granular steps), executions, messages, suppressions (`/lift` + `/deactivate` aliases), automation reporting (`/reporting/automations/*` and spec-aligned `/reports/automation/*` aliases)
- **Cooldown / max-execution / repeat** constraints per workflow per client

### Workflow execution lifecycle

1. Domain event or admin action fires `MarketingAutomationTriggerService`
2. Active workflows for tenant + trigger are located
3. `MarketingWorkflowExecutionService::createExecution` evaluates eligibility (consent, suppression, cooldown, max executions)
4. Execution steps are materialised from workflow steps
5. `processExecution` runs synchronously: `send_message` renders template + creates message + simulates dispatch; `wait` completes immediately
6. Execution transitions to completed/failed/skipped; audit events written

### Step types

| Step type | 10B status |
|-----------|------------|
| `send_message` | Fully operational |
| `wait` | Fully operational (records completion; delay honoured via `scheduled_for`) |
| `tag_client` | Stored only � deferred |
| `internal_note` | Stored only � deferred |

### Audit events (10B additions)

`marketing_workflow.{created,updated,status_updated,steps_updated,test_run}`, `marketing_workflow_step.{created,updated,reordered,archived}`, `marketing_workflow_execution.{created,cancelled,completed,failed}`, `marketing_message.{delivered,unsubscribed}`, `marketing_suppression.{created,lifted,reactivated}` (plus existing `marketing_message.{sent,failed}` from dispatch). `/deactivate` is an alias for `/lift`.

### Deferred (not 10B)

- Real provider delivery and webhooks (Module 13)
- Public preference centre / client self-service unsubscribe UI
- `tag_client` and `internal_note` step execution
- Visual workflow builder, drip branching, A/B testing

Scheduled cadence + pending dispatch is handled by `marketing:run-scheduled` (see Extension: Scheduled cadences).

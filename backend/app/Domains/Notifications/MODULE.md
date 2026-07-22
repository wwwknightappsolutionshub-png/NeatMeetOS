# Notifications domain

## Scope (Module 11A)

Notifications owns the **operational communication log** for outbound messages that are **not** marketing automations: booking confirmations/reminders/cancellations, payment links/reminders, membership renewal/expiry notices, waitlist contact attempts, and manual staff-to-client messages.

Delivery is **simulation-first** (`provider = simulation`) — no real email/SMS/WhatsApp provider integrations in 11A.

### In scope for 11A

- **Operational templates** (`notifications_templates`) — separate from marketing templates; categories: booking, payments, membership, crm, general
- **Communication log** (`notifications_messages`) — canonical aggregate with cross-domain nullable FKs (appointment, checkout, payment transaction, membership, marketing workflow execution)
- **Send attempts** (`notifications_message_attempts`) — per-message provider attempts with request/response payloads
- **Operational preferences** (`notification_preferences`) — per-client projection derived from CRM consent; gates delivery by channel + category
- **Tenant settings** (`notification_automation_settings`) — enable/disable operational notification types, sender identity, reminder defaults
- **Simulation dispatch** — `NotificationDispatchSimulationService` transitions queued → processing → sent/failed/suppressed
- **Trigger orchestration** — `NotificationTriggerService` callable by Booking/Payments/Memberships/CRM; Booking create/cancel/lifecycle cancel and online staff alerts are wired
- **Desk chat (extension)** — `POST /notifications/messages/desk` creates tenant-scoped internal notes (`metadata.desk_chat`); admin shell Chat drawer + notification bell use the messages API
- **Client timeline** — `NotificationTimelineService` normalises `notifications_messages` for admin client communication history
- **Reporting** — summary, failures, by-purpose breakdowns
- **Admin APIs** — `/api/v1/admin/notifications/*`

### Ownership boundaries

| Domain | Owns | Notifications may |
|--------|------|-------------------|
| **Notifications** | Operational templates, messages, attempts, preferences projection, settings, timeline, reporting | — |
| **Marketing** | Campaigns, workflows, suppressions, marketing messages | Read-only reference via `marketing_workflow_execution_id` on messages |
| **CRM** | Client profile, legal consent history | Read consent via `ClientConsentService`; sync to `notification_preferences` (never mutates CRM consent) |
| **Booking** | Appointments, waitlist | Read appointment/waitlist facts; trigger service creates notification records |
| **Payments** | Payment transactions | Read payment facts; trigger service creates payment link/reminder records |
| **Memberships** | Client memberships | Read membership facts; trigger service creates renewal/expiry notices |
| **Integrations** | Real provider adapters | Not implemented in 11A |

### Operational vs marketing

| Aspect | Marketing (10A/10B) | Notifications (11A) |
|--------|---------------------|---------------------|
| Purpose | Campaigns, journeys, nudges, win-back | Operational transactional comms |
| Consent | Marketing consent + suppressions | Operational preference projection from CRM consent |
| Templates | `marketing_templates` | `notifications_templates` |
| Messages | `marketing_messages` | `notifications_messages` |
| Triggers | Workflow engine + domain events | Callable `NotificationTriggerService` methods |

### Consent projection

`NotificationPreferenceService::syncFromConsent()` reads CRM `ClientConsentService::currentState()` and projects `allow_email` / `allow_sms` / `preferred_channel` onto `notification_preferences`. CRM consent records remain the legal source of truth and are never mutated by this domain.

`NotificationMessageService::createSystemMessage()` checks `NotificationPreferenceService::allowsDelivery()` before dispatch. Blocked messages are created with `status = suppressed`.

### Audit events

- `notification_template.{created,updated,archived}`
- `notification_message.{created,cancelled,delivered,sent,failed,suppressed}`
- `notification_preference.{updated,synced_from_consent}`
- `notification_automation_settings.updated`

### Admin APIs

| Route | Permission |
|-------|------------|
| `GET/POST/PUT/PATCH archive /notifications/templates` | `notifications.view` / `notifications.manage` |
| `GET/POST manual/cancel/mark-delivered /notifications/messages` | `notifications.view` / `notifications.manage` |
| `GET/PUT/POST sync-from-consent /notifications/preferences/{clientId}` | `notifications.view` / `notifications.manage` |
| `GET/PUT /notifications/settings` | `notifications.view` / `notifications.manage` |
| `GET /notifications/timeline/clients/{clientId}` | `notifications.view` |
| `GET /notifications/reporting/{summary,failures,by-purpose}` | `notifications.reporting.view` |

### Trigger service (callable integration layer)

`NotificationTriggerService` exposes:

- `sendBookingConfirmation(Appointment)`
- `sendBookingReminder(Appointment)`
- `sendBookingCancellation(Appointment)`
- `sendOnlineBookingStaffAlert(Appointment)`
- `sendWaitlistContact(WaitlistEntry)`
- `sendPaymentLink(PaymentTransaction)`
- `sendPaymentReminder(PaymentTransaction)`
- `sendMembershipExpiryNotice(ClientMembership)`
- `sendMembershipRenewalNotice(ClientMembership)`
- `sendManualClientMessage(Client, payload)`

Booking lifecycle hooks call confirmation on create, cancellation on cancel/status→cancelled, and staff alert for `booking_source=online`. Reminder dispatch is scheduled (not Marketing).

### Extension: In-app + sample templates

- Channel **`in_app`** delivers to the member PWA inbox via CRM `ClientNotice` (`notification.in_app`), parallel to Marketing’s marketing.in_app notices.
- Triggers fan out **email (or SMS fallback) + in-app** for booking/payment/membership/CRM operational sends.
- `POST /admin/notifications/templates/install-samples` installs editable email + in-app starters per purpose (booking, payments, membership, CRM, general).

### Deferred (not 11A)

- Real email/SMS/WhatsApp provider integrations
- Public notification preference centre
- Push/mobile app delivery (Web Push remains Marketing-first)
- Inbound webhook processing / omnichannel inbox

> Frontend admin UI shipped in **11B** (`/admin/notifications/*` and the client Communications tab), verified against these backend contracts in the Step 20 audit.

See `docs/local-development.md` for permissions, demo flows, and API reference.

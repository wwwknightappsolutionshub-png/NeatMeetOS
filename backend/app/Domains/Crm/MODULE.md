# CRM domain — Module 2A + 2B

**Roadmap module:** 2 — Client CRM (slices 2A, 2B)

## Implemented (2A)

- Client profiles (CRUD, search/filter, archive)
- Tags (create, assign to clients, filter by tag)
- Internal notes (author + timestamp + type)
- Consent history (append-only records, current state derived from latest per type)
- Client timeline (`client_timeline_events` — unified activity stream)

## Implemented (2B)

- **Formula records** — salon colour/treatment formulas per client (`client_formulas`)
- **Photo gallery foundation** — asset-reference records (`client_photos`); storage path/URL registration (no full upload pipeline yet)
- **Document attachments foundation** — asset-reference records (`client_documents`)
- **Profile enrichment** — `preferred_team_member_id`, `preferences` (JSON), `loyalty_display_status` (display-only placeholder)
- **Derived read model** — `communication_preferences` on client show (from consent history, not stored on client)
- **Timeline events** — formula created/updated/archived, photo/document added/archived, profile preferences updated
- **Public CRM join QR (extension)** — short public form at `/join/{tenantSlug}`; WhatsApp required; branded welcome email on signup (when email provided) with membership/loyalty offers + membership PWA link; admin QR at Settings → CRM join QR
- **Membership PWA portal (extension)** — `/member/{tenantSlug}` login via email + WhatsApp; unlocks Membership/Loyalty booking tiers
- **Client referral programme (extension)** — members share `?ref=CODE` join links; referrer earns +100 loyalty on successful **new** join; referred earns +300 on first plan/package purchase; WhatsApp share URL + typed email invites (max 20); no device contact-list access

## Permissions

| Permission | Purpose |
|---|---|
| `crm.view` | List/view clients, tags, notes, consents, timeline, formulas, photos, documents |
| `crm.manage` | Create/update clients, tags, notes, consent records, formulas, photos, documents |

## Model decisions

- **Consent:** append-only `client_consent_records`; current state = latest record per `consent_type`
- **Timeline:** dedicated `client_timeline_events` table (not audit_logs) for client-facing activity; audit_logs still used for admin mutations
- **Marketing opt-in:** represented via consent model only (no duplicate flags on client)
- **Assets:** `storage_path` string references (same pattern as tenant branding `logo_url`); binary upload deferred
- **Loyalty display:** `loyalty_display_status` on client is CRM display placeholder only; Memberships module owns authoritative loyalty later
- **Referrals:** `client_referral_invites` (unique code per tenant), `client_referral_conversions` (idempotent per referred client), `client_referral_settings` (points + share copy), `client_referral_email_sends` (invite audit). Attribution columns on `clients`. Loyalty entry types `referral_referrer` / `referral_referred`.

## Deferred (later modules)

- Treatment consultation forms, patch tests, contraindications (Consultation)
- Before/after treatment gallery workflows
- Binary file upload / media processing pipeline
- Loyalty wallet / points ledger (Memberships)
- Campaign sending (Marketing Automation)
- Booking linkage on profile
- Saved segment definitions beyond tag filters
- Admin UI for referral programme settings (defaults apply per tenant on first use)

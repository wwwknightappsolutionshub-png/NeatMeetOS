# Module: Growth Assessment (Free Salon Growth Assessment)

Platform-owned, **pre-tenant** lead generation diagnostic. Not mixed into salon CRM clients.

## Public API

- `POST /api/v1/growth-assessments` — submit (Turnstile + honeypot + `throttle:public-signup` + `ip.ban`)
- `GET /api/v1/growth-assessments/{publicToken}` — public results re-fetch
- `POST /api/v1/growth-assessments/{publicToken}/whatsapp` — request WhatsApp delivery via platform Genius outbound

## Platform API

- `GET /api/v1/platform/growth-assessments`
- `GET /api/v1/platform/growth-assessments/{id}`
- `PATCH /api/v1/platform/growth-assessments/{id}` — lead status, notes, assignee, follow-up

## Scoring (rule-based)

- **Visibility** = knows visitors + knows returners + tracking method + knows due-return timing (max 100)
- **Retention** = return % band + systematic encourage methods + due-return timing
- **Revenue visibility** = avg spend known + knows missed revenue + software satisfaction
- **Re-engagement** = encourage methods + due-return timing
- **Overall** = 0.30·V + 0.30·R + 0.20·Rev + 0.20·Re

## Revenue opportunity (indicative)

`customers_band_mid × spend_band_mid_£ × non_return_rate` → pounds × 100 = cents

Non-return rate derived from stated return % (e.g. under 20% return → 0.70 non-return).

## Reuse

- `AuthMailService`-style HTML via `SalonGrowthAssessmentMailService`
- `PlatformWhatsAppSettingsService::sendOperational`
- Turnstile / honeypot / public-signup throttle patterns

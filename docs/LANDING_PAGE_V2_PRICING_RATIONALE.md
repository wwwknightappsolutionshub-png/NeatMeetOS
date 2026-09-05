# Landing Page V2 — Pricing Rationale

**Checked:** 5 September 2026  
**Purpose:** Public UK competitive benchmark to set NeatMeet OS landing-page pricing (Basic / Advanced / Diamond).  
**Note:** Landing presents **Advanced**; billing slug remains `pro` for existing signup/API compatibility.

## Competitor snapshot (public / third-party)

| Competitor | Model | Indicative monthly price (UK) | Relevant capabilities | Source / notes |
|---|---|---|---|---|
| Booksy | Flat subscription + optional marketplace Boost | ~£30–£40 + VAT base; extra staff fees vary by source (£5–£20 reported) | Booking, SMS reminders, basic loyalty stamps, POS via Booksy terminal | Tavix salon pricing comparison (Mar 2026); ReeveOS UK comparison (2026) |
| Fresha | Per-seat subscription + marketplace commission | Solo ~£14.95/mo; Team ~£9.95/staff/mo + VAT; **20%** on new marketplace clients; loyalty add-on ~£49.95/mo | Booking, POS, memberships; marketplace acquisition; loyalty paid add-on | Tavix (Mar 2026); Bizzly Fresha vs Treatwell (2026) |
| Treatwell Connect | Subscription (often opaque) + high marketplace commission | Subscription figures vary (~£10–£35 reported regionally); **35%** on new marketplace clients | Marketplace discovery + calendar; loyalty often marketplace-owned | Tavix (Mar 2026); DoTheBeauty (2026) |
| Timely | Per-staff subscription | Build ~£21 / Elevate ~£32 / Innovate ~£37 **per staff**/mo | Booking, reporting; loyalty/reviews on higher tiers | Tavix (Mar 2026) |
| Phorest | Quote-only enterprise suite | Community/third-party estimates commonly **~£100–£300+/mo**; plus UK online booking fees £0.60–£1.50/booking | Full salon suite, marketing, reactivation, reporting | Phorest public site = quote only; Bizzly / DoTheBeauty Phorest pricing guides (2026) |

Marketplace platforms (Fresha / Treatwell) can look cheap on subscription but become expensive via **commission**. NeatMeet is positioned as a **flat operating-system subscription** with growth/retention intelligence — not a new-client marketplace.

## NeatMeet comparison

NeatMeet OS includes (where plan-enabled): booking, CRM, POS/payments, memberships/loyalty, marketing, notifications, analytics / **Business Performance Intelligence**, gallery/lookbook, ecommerce (higher tiers), client import.

Differentiation vs booking-first tools: **customer visibility, retention, re-engagement, loyalty, and actionable business performance** — not calendar alone.

## Recommended public pricing (Landing V2)

| Landing name | Billing slug | Monthly (ex VAT presentation) | Positioning |
|---|---|---|---|
| **Basic** | `basic` | **£59** | Independents & smaller salons organising day-to-day ops + CRM foundation |
| **Advanced** (recommended) | `pro` | **£99** | Growing salons: retention tools, loyalty/memberships, analytics / BPI |
| **Diamond** | `diamond` | **£179** | Established / multi-location: full growth suite, marketing, ecommerce, integrations |

### Rationale

1. **Above** Booksy/Fresha “calendar + marketplace” entry pricing because NeatMeet sells an operating system (CRM + retention + BPI), not discovery commission.
2. **Transparent** vs Phorest quote-only; Advanced at **£99** sits near the lower end of community Phorest estimates without claiming “cheaper than Phorest”.
3. **Below** old NeatMeet V1 list (£49 / £129 / £299) at the top end — Diamond **£179** is more commercially realistic for UK multi-location without looking arbitrary.
4. **Basic £59** is a deliberate step above pure booking tools while remaining approachable for single-chair / small teams.
5. **No annual toggle** on the landing page: existing billing architecture seeds **monthly** plans only (`INTERVAL_MONTHLY`). Do not advertise “save X% annually” until annual billing exists.

## Claims we do **not** make

- “Cheapest salon software”
- Guaranteed savings vs named competitors
- Marketplace commission comparisons as NeatMeet features
- Annual discount percentages

## Implementation note

Landing CTAs for Advanced should use `desired_plan_slug=pro` (or signup default plan `pro`). Display name **Advanced** is marketing-only until/unless the DB plan `name` is renamed.

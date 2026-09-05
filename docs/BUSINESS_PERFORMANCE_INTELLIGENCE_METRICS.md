# Business Performance Intelligence — Metric Definitions

**Module:** Analytics extension (`BusinessPerformanceIntelligenceService`)  
**Audience:** Tenant owners / managers  
**Purpose:** Every KPI answers *what to do today*, not only *what happened yesterday*.  
**Rule:** Read-only over existing CRM, Booking, Payments, POS, Memberships, and Marketing tables. No parallel warehouses. No black-box ML.

Canonical API: `GET /api/v1/admin/analytics/intelligence`  
Permission: `analytics.view`  
Optional filters: `location_id`, `provider_id` (applied where the underlying tables support them).

---

## 1. Vocabulary

| Term | Definition |
|------|------------|
| **Served appointment** | `appointments.status` ∈ `{checked_in, completed}` |
| **Window (day)** | Calendar day in app timezone: `[startOfDay, endOfDay]` |
| **Window (week)** | Monday 00:00 → now (inclusive end) |
| **Window (month)** | First day of month 00:00 → now |
| **Identified customer** | CRM `clients` row with usable contact: `TRIM(email) ≠ ''` **OR** `TRIM(COALESCE(phone_normalized, phone)) ≠ ''` |
| **Anonymous customer** | A served appointment with `client_id IS NULL`, **OR** linked client that is **not** identified |
| **Identifiable visit unit** | One distinct identified `client_id` in a window |
| **Anonymous visit unit** | One served appointment that is anonymous (each appointment counts as 1) |

Customer **visibility** is central: owners must see how much of demand they can re-market.

---

## 2. Section A — Business performance

| KPI | Formula | Primary sources | Timestamp |
|-----|---------|-----------------|-----------|
| `customers_served_today` | Identifiable visit units today + anonymous visit units today | `appointments` ⋈ `clients` | `appointments.starts_at` |
| `customers_served_week` | Same, week window | same | same |
| `customers_served_month` | Same, month window | same | same |
| `total_revenue_cents` | `payments.net_collected_cents` + `pos.gross_sales_cents` for **month-to-date** (operational, not accounting) | `payment_transactions`, `payment_refunds`, `commerce_checkouts` | payments `created_at`; POS `completed_at` |
| `average_spend_cents` | `total_revenue_cents ÷ max(1, customers_served_month)` | derived | — |
| `new_customers_month` | Identified clients served this month whose **first** served appointment ever falls in the month | `appointments` | `starts_at` |
| `returning_customers_month` | Identified clients served this month who had ≥1 served appointment **before** month start | `appointments` | `starts_at` |
| `walk_ins_month` | Count appointments with `booking_source = walk_in` and `starts_at` in month (any active/served/cancelled/no_show except waiting-only optional — **count all non-waiting walk-ins** with `starts_at` in month) | `appointments` | `starts_at` |
| `online_bookings_month` | Count appointments with `booking_source = online` and `starts_at` in month | `appointments` | `starts_at` |

**Notes**

- Revenue reuses `RevenueAnalyticsService` semantics (inbound succeeded payments − refunds + completed POS gross).
- New vs returning is computed **only for identified clients**. Anonymous units are reported separately under visibility, not as “new”.

---

## 3. Section B — Customer intelligence (visibility)

| KPI | Formula | Sources |
|-----|---------|---------|
| `identified_served_month` | Distinct identified clients with ≥1 served appointment this month | `appointments` ⋈ `clients` |
| `anonymous_served_month` | Count of anonymous served appointments this month | `appointments` ⋈ `clients` |
| `visibility_rate` | `identified_served_month ÷ max(1, identified_served_month + anonymous_served_month)` | derived |
| `returning_rate` | `returning_customers_month ÷ max(1, identified_served_month)` | derived |
| `first_time_rate` | `new_customers_month ÷ max(1, identified_served_month)` | derived |
| `unidentified_gap_count` | `anonymous_served_month` (same value; named for Action Center) | derived |

**Product meaning**

- High visibility → more remessaging / membership / win-back leverage.
- High anonymity → Action Center pushes “capture contact at desk / join QR”.

---

## 4. Section C — Repeat revenue opportunity

Defaults (tenant-tunable later; v1 constants):

- `typical_cycle_days` = **35** (median salon revisit heuristic)
- `due_soon_window_days` = **7** (clients whose next expected visit falls within the next 7 days)
- `overdue_grace_days` = **7** past cycle before “overdue”

| KPI | Formula | Sources |
|-----|---------|---------|
| `avg_identified_spend_cents` | Month operational revenue ÷ max(1, identified_served_month) | derived from A+B |
| `clients_due_soon` | Identified active clients with last served visit in `[now − cycle, now − (cycle − due_soon)]`, **no** future booking (`pending/confirmed`) | `appointments`, `clients` |
| `clients_overdue` | Identified active clients with last served visit **before** `now − (cycle + grace)`, no future booking | same |
| `estimated_opportunity_cents` | `(clients_due_soon + clients_overdue) × avg_identified_spend_cents` | derived |
| `crm_joiners_without_visit` | Clients with `membership_joined_at` set and **zero** served appointments | `clients`, `appointments` |

Only **identified** clients enter due/overdue pools (anonymous cannot be nudged).

---

## 5. Section D — Action center

Each task is a **count + deep link** into an existing module. BPI does not own the underlying workflows.

| Task key | Count definition | Deep link |
|----------|------------------|-----------|
| `capture_anonymous_contacts` | `unidentified_gap_count` (month) | `/admin/settings/crm-join-qr` |
| `send_rebook_reminders` | `clients_due_soon + clients_overdue` | `/admin/marketing` |
| `nudge_crm_joiners` | `crm_joiners_without_visit` | `/admin/next-visit` |
| `renew_memberships` | Active `client_memberships` with `current_period_end` within 14 days **or** status indicating ending/lapsed if present | `/admin/memberships/subscriptions` |
| `review_failed_payments` | Failed `payment_transactions` created in last 30 days | `/admin/payments/failed` |
| `review_pending_deposits` | Appointments with `deposit_status = pending` and `starts_at` ≥ today − 7d | `/admin/bookings` |
| `review_payment_documents` | Reservation payment documents awaiting review (status pending/submitted if column exists; else 0 + link) | `/admin/payments/documents` |
| `expire_packages` | `client_packages` with `quantity_remaining > 0` and `expires_at` within 14 days | `/admin/memberships/client-packages` |

Tasks with `count = 0` are still returned (UI may de-emphasize).

---

## 6. Section E — Business insights

Rule-based copy only. Each insight has `code`, `severity` (`info` \| `warning` \| `success`), `message`, optional `action_href`.

| Code | When | Message pattern |
|------|------|-----------------|
| `visibility_low` | `visibility_rate < 0.70` | “Only {pct}% of customers served this month are identifiable. Capture email/WhatsApp at the desk or promote the join QR.” |
| `visibility_strong` | `visibility_rate ≥ 0.85` | “Strong customer visibility ({pct}%). You can safely run remessaging and membership offers.” |
| `anonymous_gap` | `anonymous_served_month ≥ 3` | “{n} visits this month have no contact details — those customers cannot be rebooked automatically.” |
| `repeat_opportunity` | `estimated_opportunity_cents > 0` | “About {money} in repeat visits sits with customers due soon or overdue. Open Marketing to send reminders.” |
| `joiners_idle` | `crm_joiners_without_visit ≥ 1` | “{n} CRM joiners have not visited yet. Nudge next-visit or share the booking link.” |
| `failed_payments` | failed payments count > 0 | “{n} failed payments need review in Payments.” |
| `walk_in_heavy` | walk-ins > online and walk-ins ≥ 5 | “Walk-ins outpace online bookings this month — push online booking QR to fill quieter slots.” |
| `returning_healthy` | `returning_rate ≥ 0.40` | “{pct}% of identifiable customers this month are returning — protect them with membership/package offers.” |

No ML, no opaque scores. Thresholds are documented constants in the service.

---

## 7. Consistency with existing Analytics

| Existing report | Relationship to BPI |
|-----------------|---------------------|
| `/analytics/overview` | Unchanged historical KPI board |
| `/analytics/revenue` | BPI reuses payment/POS formulas via `RevenueAnalyticsService` |
| `/analytics/bookings` | Walk-in / no-show detail remains there; BPI surfaces action-oriented counts |
| `/analytics/clients` | Growth/consent remain there; BPI adds **visibility** lens |
| Marketing win-back | BPI deep-links; does not generate campaigns |
| Payments failed list | BPI deep-links; does not fork CRUD |

---

## 8. Non-goals

- Finance-grade P&L / tax accounting  
- Cohort forecasting / ML propensity  
- Mutating CRM/booking/payment records from this endpoint  
- Materialized rollup tables (v1 is live aggregation)

---

## 9. Change control

Any formula change **must** update this document and the PHPUnit assertions in `Module12CBusinessPerformanceIntelligenceTest` in the same PR.

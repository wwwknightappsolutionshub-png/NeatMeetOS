# Staff domain — Module 3A

**Roadmap module:** 3 — Staff, Rota & Compensation (slice 3A)

## Implemented

- **Provider operational profile** (`staff_profiles` 1:1 with `team_members`)
  - `is_bookable`, `show_in_online_booking`, `accepts_walk_ins`
  - `booking_display_name`, `internal_notes`
  - `default_workspace_id`, `min_lead_time_minutes`, `buffer_minutes` (booking placeholders)
- **Recurring weekly availability** (`staff_availability_rules`)
  - day of week (1=Monday … 7=Sunday), start/end time, location, optional workspace
- **Time-off / absences** (`staff_absences`)
  - categories: holiday, sickness, unavailable, training, other
  - active/cancelled status
- **Operating scope**
  - `staff_operating_locations` pivot for allowed locations
  - `team_member_workspace` pivot (Identity) for allowed workspaces

## Permissions

| Permission | Purpose |
|---|---|
| `staff.view` | List/view providers, availability, absences |
| `staff.manage` | Update profile, availability, absences, operating scope |

## Model decisions

- **StaffProfile** extends TeamMember operationally; Identity still owns auth/employment CRUD
- **Availability** is template-based (weekly recurring windows), not generated bookable slots
- **Absences** are booking-blocking operational records, not payroll leave approval
- **Booking module** will consume availability rules + absences + profile flags later

## Deferred (3B+ / Phase 4)

- Commission engine, payroll exports
- Chair/room rent billing
- Productivity KPIs, goal tracking
- Booking slot generation and conflict resolution
- Service-level booking rules

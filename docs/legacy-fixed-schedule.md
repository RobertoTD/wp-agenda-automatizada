# Legacy fixed schedule (`LEGACY_FIXED_SCHEDULE`)

Deprecated weekly “horario fijo” availability, kept for backward compatibility. **Do not extend.** Remove only with a dedicated migration plan.

## What it is

- Weekly intervals stored in `aa_schedule` (per weekday, enabled + intervals).
- Display names in `aa_service_schedule` and `aa_staff_schedule`.
- Public/admin booking option `fixed::<service name>` when `aa_service_schedule` is non-empty.
- Slots and calendar days derived via `DateUtils.getDayIntervals()` + `SlotCalculator` / `CalendarAvailabilityService`.

Prefer **assignment-based** availability (`aa_assignments`, `assignment_id` on reservations). See `docs/fast-appointment-vs-assignment-availability.md` for Motor A vs B.

## WP options

| Option | Role |
|--------|------|
| `aa_schedule` | Weekly enabled days and time intervals |
| `aa_service_schedule` | Label for `fixed::` service option |
| `aa_staff_schedule` | Label on calendar schedule overlay |

Registered in `views/admin-controls.php`. Settings UI is **hidden by default** (`AA_SHOW_LEGACY_FIXED_SCHEDULE_UI` / filter `aa_show_legacy_fixed_schedule_ui` in `includes/admin/ui/modules/settings/index.php`).

## `fixed::` prefix

Emitted in frontend shortcode (`wp-agenda-automatizada.php`) and admin reservation modal when `aa_service_schedule` is set. Stripped on save in `CreateReservationUseCase` and confirm helpers. Detected in JS via `isFixedServiceKey` / `startsWith('fixed::')`.

## `assignment_id IS NULL`

Not identical to “fixed UI”, but coupled for **legacy busy** occupancy:

- `ReservationsModel::get_internal_busy_slots()` — confirmed, `assignment_id IS NULL`.
- Used by `availability-controller.php` and admin `layout.php` as `aa_local_availability.local_busy`.
- Historical reservations without assignment still use this path.

Assignment-based bookings use `assignment_id` and separate busy logic (`busyRangesAssignments`, etc.).

## Current product state

| Area | Status |
|------|--------|
| Settings UI | Hidden (flag/filtro off by default) |
| Runtime (options, `fixed::`, slots, overlays) | **Conserved** |
| Admin calendar init | Does **not** require non-empty `aa_schedule` (Ciclo 1) |
| Schedule overlay column | Still renders if `aa_schedule` has intervals |

## Rules for contributors

1. Do **not** add features on `aa_schedule`, `fixed::`, or schedule overlays.
2. New availability and booking flows must use assignments.
3. Code touched by legacy paths should carry `LEGACY_FIXED_SCHEDULE` comments (grep the repo).
4. Full removal requires: stop `fixed::` booking, migrate or retire NULL-assignment busy rules, data migration for options, then delete dead code in a planned series of PRs.

## Related files (hot spots)

- Options: `views/admin-controls.php`, `includes/infrastructure/wp/Schema.php`
- `fixed::` selects: `wp-agenda-automatizada.php`, `includes/admin/ui/modals/reservation/index.php`
- JS availability: `assets/js/services/availability/calendarAvailabilityService.js`, `frontendAssignmentsController.js`, `adminReservationAssignmentFlowController.js`
- Busy NULL: `includes/models/ReservationsModel.php`, `includes/controllers/availability-controller.php`
- Calendar overlay: `calendar-module.js`, `calendar-section/calendar-assignments.js`

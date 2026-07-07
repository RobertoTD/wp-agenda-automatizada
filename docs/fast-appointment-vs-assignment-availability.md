# Fast Appointment vs Assignment-based Availability

Two independent availability motors coexist in this plugin.
They must NOT share logic or assumptions.

---

## Motor A — Assignment-based Availability

**Purpose:** Public calendar / reservation modal.
Dates and time slots are derived from active assignments.

| Concept              | Value                                          |
|----------------------|------------------------------------------------|
| Source of availability | Active assignments (`aa_assignments`)         |
| Source of occupancy    | Confirmed reservations scoped to assignments  |
| Role of assignments    | **Primary** — they define when and where service is offered |

### Key files

| File | Role |
|------|------|
| `assets/js/services/availability/availabilityAssignments.js` | Queries assignment dates/slots |
| `assets/js/services/availability/busyRangesAssignments.js`   | Queries busy ranges by assignment IDs |
| `assets/js/services/availability/calendarAvailabilityService.js` | Neutral orchestrator (fixed vs assignment) |
| `includes/admin/ui/modals/reservation/reservation.js` | Modal that consumes this motor |

---

## Motor B — Fast Appointment Availability

**Purpose:** Admin-only "cita rapida" modal.
All 30-min day slots are available by default; confirmed reservations remove slots.

| Concept              | Value                                          |
|----------------------|------------------------------------------------|
| Source of availability | Base 30-min day slots (00:00 – 23:30)         |
| Source of occupancy    | Confirmed reservations (`estado = 'confirmed'`) |
| Role of assignments    | **Bridge only** — maps `assignment_id → staff_id` |

### Why assignments appear at all

Reservations store `assignment_id` instead of `staff_id` directly.
To determine which staff member is busy, the service fetches
assignments of the day and builds a lookup `{ assignment_id: staff_id }`.
This is a technical bridge, not a business rule.

### Key files

| File | Role |
|------|------|
| `assets/js/services/availability/fastAppointmentTimeAvailabilityService.js` | Core motor: slots, occupancy, evaluation |
| `assets/js/services/availability/fastAppointmentPrerequisitesService.js` | Validates prerequisites (staff, services, areas) |
| `assets/js/controllers/adminFastappointmentFlowController.js` | Flow controller (date, time, client) |
| `assets/js/controllers/adminFastappointmentController.js` | Top-level controller (state, init) |
| `includes/admin/ui/modals/fastappointment/fastappointment.js` | Modal wiring |

---

## Rules — DO NOT assume

1. **Do not reuse `availabilityAssignments.js` inside Fast Appointment.**
   Fast Appointment does not derive availability from assignments.

2. **Do not reuse `fastAppointmentTimeAvailabilityService.js` inside the Reservation modal.**
   The reservation modal uses assignment-based availability.

3. **Assignments are NOT a source of occupancy.**
   They are a bridge to reach `staff_id` from `assignment_id` in confirmed reservations.

4. **The 30-min base slots are not schedule-dependent.**
   Fast Appointment generates all 48 daily slots unconditionally.
   Schedule-based intervals belong to the fixed/legacy availability flow.

5. **Slot removal rule (Fast Appointment):**
   A slot is removed only when ALL usable staff are busy.
   If at least one staff member is free, the slot stays.

6. **Grid vs appointment duration (Fast Appointment):**
   Base slot generation always uses a fixed **30-minute grid**
   (`GRID_STEP_MINUTES`). Occupancy filtering uses the **appointment
   duration** passed via `context.slotDuration` (resolved once in
   `adminFastappointmentFlowController.getEffectiveAppointmentDurationMinutes`
   and reused on submit). Do not use appointment duration as the grid step.

---

## Quick decision guide

| Question | Answer |
|----------|--------|
| "Where do I add assignment-based date filtering?" | Motor A (`availabilityAssignments.js`) |
| "Where do I change how occupied slots are calculated for admin cita rapida?" | Motor B (`fastAppointmentTimeAvailabilityService.js`) |
| "Can I call `AAAssignmentsAvailability` from Fast Appointment?" | **No.** |
| "Can I call `FastAppointmentTimeAvailabilityService` from the reservation modal?" | **No.** |
| "Where is the bridge `assignment_id → staff_id`?" | `buildAssignmentStaffBridge()` in Motor B's service |

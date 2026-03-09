/**
 * GUARDRAIL — Fast Appointment Time Availability Service
 *
 * Motor: Fast Appointment (admin only)
 *
 * Source of availability : base 30-min day slots (00:00 – 23:30)
 * Source of occupancy    : confirmed reservations (estado = 'confirmed')
 * Role of assignments    : bridge ONLY — maps assignment_id → staff_id
 *
 * This file must NOT reuse logic from availabilityAssignments.js.
 * Assignments here are NOT a source of availability; they exist in the
 * pipeline solely because reservations reference assignment_id instead
 * of staff_id directly.
 *
 * See docs/fast-appointment-vs-assignment-availability.md
 *
 * @package AgendaAutomatizada
 * @since   2.0.0
 */
(function() {
    'use strict';

    // ──────────────────────────────────────────────
    // AJAX helpers
    // ──────────────────────────────────────────────

    function getAjaxUrl() {
        if (window.wpaa_vars && window.wpaa_vars.ajax_url) {
            return window.wpaa_vars.ajax_url;
        }

        if (window.ajaxurl) {
            return window.ajaxurl;
        }

        throw new Error('AJAX URL no disponible para Fast Appointment');
    }

    async function requestJson(action, body) {
        var formData = new FormData();
        var payload = body || {};

        formData.append('action', action);

        Object.keys(payload).forEach(function(key) {
            if (typeof payload[key] !== 'undefined' && payload[key] !== null) {
                formData.append(key, payload[key]);
            }
        });

        var response = await fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Error HTTP ' + response.status + ' en ' + action);
        }

        var result = await response.json();

        if (!result || result.success !== true) {
            throw new Error(
                result && result.data && result.data.message
                    ? result.data.message
                    : ('Respuesta invalida en ' + action)
            );
        }

        return result.data || {};
    }

    // ──────────────────────────────────────────────
    // Time formatting
    // ──────────────────────────────────────────────

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatDate(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function formatTimeFromMinutes(totalMinutes) {
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;

        return pad2(hours) + ':' + pad2(minutes);
    }

    // ──────────────────────────────────────────────
    // Slot generation
    // ──────────────────────────────────────────────

    function buildBaseSlots(slotDuration) {
        var slots = [];

        for (var minutes = 0; minutes < 24 * 60; minutes += slotDuration) {
            var time = formatTimeFromMinutes(minutes);

            slots.push({
                value: time,
                label: time,
                available: true
            });
        }

        return slots;
    }

    // ──────────────────────────────────────────────
    // Staff helpers
    // ──────────────────────────────────────────────

    function normalizeUsableStaff(usableStaff) {
        if (!Array.isArray(usableStaff)) {
            return [];
        }

        return usableStaff.map(function(staff) {
            return {
                id: parseInt(staff.id, 10),
                name: staff.name || ''
            };
        }).filter(function(staff) {
            return staff.id > 0;
        });
    }

    // ──────────────────────────────────────────────
    // Time arithmetic
    // ──────────────────────────────────────────────

    function extractTimePart(dateTimeString) {
        if (!dateTimeString) {
            return '00:00';
        }

        var raw = String(dateTimeString).trim();

        if (raw.indexOf(' ') !== -1) {
            raw = raw.split(' ')[1];
        }

        return raw.slice(0, 5);
    }

    function timeToMinutes(timeString) {
        var parts = String(timeString || '00:00').split(':');
        var hours = parseInt(parts[0], 10) || 0;
        var minutes = parseInt(parts[1], 10) || 0;

        return (hours * 60) + minutes;
    }

    function rangesOverlap(startA, endA, startB, endB) {
        return startA < endB && endA > startB;
    }

    // ──────────────────────────────────────────────
    // Data fetching  (assignment bridge)
    //
    // Assignments are fetched ONLY as a technical bridge
    // to map assignment_id → staff_id.  They are NOT a
    // source of availability for Fast Appointment.
    // ──────────────────────────────────────────────

    async function fetchAssignmentsForDate(date, usableStaff) {
        var data = await requestJson('aa_get_assignments');
        var assignments = Array.isArray(data.assignments) ? data.assignments : [];
        var targetDate = window.DateUtils.extractYmd(date);
        var usableStaffIds = usableStaff.map(function(staff) {
            return staff.id;
        });

        var assignmentsOfDay = assignments.filter(function(assignment) {
            var assignmentDate = window.DateUtils.extractYmd(assignment.assignment_date);
            var assignmentStaffId = parseInt(assignment.staff_id, 10);

            return assignmentDate === targetDate &&
                usableStaffIds.indexOf(assignmentStaffId) !== -1;
        });

        console.log('[FastAppt] Assignments del dia (bridge):', assignmentsOfDay.length, 'de', assignments.length, 'totales');

        return assignmentsOfDay;
    }

    /**
     * Fetch confirmed-reservation busy ranges using assignment IDs as bridge.
     *
     * The backend endpoint (aa_get_busy_ranges_by_assignments) queries
     * wp_aa_reservas WHERE estado = 'confirmed' AND assignment_id IN (…).
     * This is the ONLY source of occupancy for Fast Appointment.
     *
     * @param {string} date           YYYY-MM-DD
     * @param {Array}  assignmentIds  Numeric IDs (or string-numeric) of assignments for the day
     * @returns {Promise<Array>}      busy_ranges from confirmed reservations
     */
    async function fetchConfirmedReservationRanges(date, assignmentIds) {
        var ids = Array.isArray(assignmentIds)
            ? assignmentIds.map(function(id) { return parseInt(id, 10); }).filter(function(id) { return id > 0; })
            : [];

        ids = Array.from(new Set(ids));

        if (!ids.length) {
            return [];
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_busy_ranges_by_assignments');
        formData.append('date', date);
        ids.forEach(function(id) {
            formData.append('assignment_ids[]', String(id));
        });

        var response = await fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Error HTTP ' + response.status + ' en aa_get_busy_ranges_by_assignments');
        }

        var data = await response.json();

        if (!data || data.success !== true) {
            throw new Error(
                data && data.data && data.data.message
                    ? data.data.message
                    : 'Respuesta invalida en aa_get_busy_ranges_by_assignments'
            );
        }

        var ranges = Array.isArray(data.data && data.data.busy_ranges) ? data.data.busy_ranges : [];

        console.log('[FastAppt] Confirmed reservation ranges:', ranges.length);

        return ranges;
    }

    // ──────────────────────────────────────────────
    // Bridge mapping:  assignment_id → staff_id
    //
    // This is the bridge that lets us attribute a confirmed
    // reservation's busy range to a specific staff member.
    // ──────────────────────────────────────────────

    function buildAssignmentStaffBridge(assignments) {
        return assignments.reduce(function(acc, assignment) {
            var assignmentId = parseInt(assignment.id, 10);
            var staffId = parseInt(assignment.staff_id, 10);

            if (assignmentId > 0 && staffId > 0) {
                acc[assignmentId] = staffId;
            }

            return acc;
        }, {});
    }

    function buildStaffBusyMap(usableStaff, assignmentToStaff, busyRanges) {
        var staffBusy = {};

        usableStaff.forEach(function(staff) {
            staffBusy[staff.id] = [];
        });

        busyRanges.forEach(function(range) {
            var assignmentId = parseInt(range.assignment_id, 10);
            var staffId = assignmentToStaff[assignmentId];

            if (!staffId) {
                return;
            }

            if (!Array.isArray(staffBusy[staffId])) {
                staffBusy[staffId] = [];
            }

            staffBusy[staffId].push({
                start: range.start,
                end: range.end
            });
        });

        return staffBusy;
    }

    // ──────────────────────────────────────────────
    // Slot evaluation
    //
    // Rule: a slot is removed ONLY when every usable
    // staff member is busy during that interval.
    // If at least one staff is free, the slot stays.
    // ──────────────────────────────────────────────

    function evaluateSlots(slots, slotDuration, usableStaff, staffBusy) {
        var removedSlots = [];
        var availableSlots = [];

        slots.forEach(function(slot) {
            var slotStart = timeToMinutes(slot.value);
            var slotEnd = slotStart + slotDuration;

            var busyCount = usableStaff.filter(function(staff) {
                var ranges = Array.isArray(staffBusy[staff.id]) ? staffBusy[staff.id] : [];

                return ranges.some(function(range) {
                    var rangeStart = timeToMinutes(extractTimePart(range.start));
                    var rangeEnd = timeToMinutes(extractTimePart(range.end));

                    return rangesOverlap(slotStart, slotEnd, rangeStart, rangeEnd);
                });
            }).length;

            var allBusy = usableStaff.length > 0 && busyCount === usableStaff.length;

            if (allBusy) {
                removedSlots.push(slot.value);
                return;
            }

            availableSlots.push(slot);
        });

        console.log('[FastAppt] Slot evaluation — removed:', removedSlots.length, '| available:', availableSlots.length);

        return {
            removedSlots: removedSlots,
            availableSlots: availableSlots
        };
    }

    // ──────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────

    async function getAvailabilityByDate(date, context) {
        var evaluatedDate = date || null;
        var ctx = context || {};
        var slotDuration = 30;
        var now = new Date();
        var todayStr = formatDate(now);
        var isToday = evaluatedDate === todayStr;
        var nowMinutes = (now.getHours() * 60) + now.getMinutes();
        var nowTime = pad2(now.getHours()) + ':' + pad2(now.getMinutes());
        var usableStaff = normalizeUsableStaff(ctx.usableStaff);
        var baseSlots = buildBaseSlots(slotDuration);
        var trimmedSlots = isToday
            ? baseSlots.filter(function(slot) {
                return timeToMinutes(slot.value) >= nowMinutes;
            })
            : baseSlots.slice();

        console.log('[FastAppt] getAvailabilityByDate', evaluatedDate,
            '| isToday:', isToday,
            '| baseSlots:', baseSlots.length,
            '| afterTrim:', trimmedSlots.length,
            '| usableStaff:', usableStaff.length);

        var assignmentsForDate = [];
        var assignmentToStaff = {};
        var busyRanges = [];
        var staffBusy = {};
        var slotAnalysis = {
            removedSlots: [],
            availableSlots: trimmedSlots.slice()
        };

        try {
            assignmentsForDate = await fetchAssignmentsForDate(evaluatedDate, usableStaff);
            assignmentToStaff = buildAssignmentStaffBridge(assignmentsForDate);

            var assignmentIds = Object.keys(assignmentToStaff);

            busyRanges = await fetchConfirmedReservationRanges(evaluatedDate, assignmentIds);
            staffBusy = buildStaffBusyMap(usableStaff, assignmentToStaff, busyRanges);
            slotAnalysis = evaluateSlots(trimmedSlots, slotDuration, usableStaff, staffBusy);
        } catch (error) {
            console.error('[FastAppt] Error fetching confirmed occupancy:', error);
        }

        console.log('[FastAppt] Result — slots:', slotAnalysis.availableSlots.length,
            '| removed:', slotAnalysis.removedSlots.length,
            '| busyRanges:', busyRanges.length);

        return {
            implemented: true,
            date: evaluatedDate,
            slotDuration: slotDuration,
            isToday: isToday,
            nowTime: nowTime,
            slots: slotAnalysis.availableSlots,
            source: 'confirmed_reservations_filter'
        };
    }

    window.FastAppointmentTimeAvailabilityService = {
        getAvailabilityByDate: getAvailabilityByDate
    };
})();

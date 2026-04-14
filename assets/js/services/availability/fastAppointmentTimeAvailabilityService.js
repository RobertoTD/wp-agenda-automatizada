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
            var services = Array.isArray(staff.services)
                ? staff.services.map(function(service) {
                    return {
                        id: parseInt(service.id, 10),
                        name: service.name || ''
                    };
                }).filter(function(service) {
                    return service.id > 0;
                })
                : [];

            return {
                id: parseInt(staff.id, 10),
                name: staff.name || '',
                services: services
            };
        }).filter(function(staff) {
            return staff.id > 0;
        });
    }

    function normalizeActiveServiceAreas(serviceAreas) {
        if (!Array.isArray(serviceAreas)) {
            return [];
        }

        return serviceAreas.map(function(area) {
            return {
                id: parseInt(area.id, 10),
                name: area.name || ''
            };
        }).filter(function(area) {
            return area.id > 0;
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

    async function fetchAssignmentsForDay(date) {
        var data = await requestJson('aa_get_assignments');
        var assignments = Array.isArray(data.assignments) ? data.assignments : [];
        var targetDate = window.DateUtils.extractYmd(date);

        return assignments.filter(function(assignment) {
            var assignmentDate = window.DateUtils.extractYmd(assignment.assignment_date);

            return assignmentDate === targetDate;
        });
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

    function buildAssignmentAreaBridge(assignments) {
        return assignments.reduce(function(acc, assignment) {
            var assignmentId = parseInt(assignment.id, 10);
            var areaId = parseInt(assignment.service_area_id, 10);

            if (assignmentId > 0 && areaId > 0) {
                acc[assignmentId] = areaId;
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

    function buildAreaBusyMap(activeAreas, assignmentToArea, busyRanges) {
        var areaBusy = {};

        activeAreas.forEach(function(area) {
            areaBusy[area.id] = [];
        });

        busyRanges.forEach(function(range) {
            var assignmentId = parseInt(range.assignment_id, 10);
            var areaId = assignmentToArea[assignmentId];

            if (!areaId) {
                return;
            }

            if (!Array.isArray(areaBusy[areaId])) {
                areaBusy[areaId] = [];
            }

            areaBusy[areaId].push({
                start: range.start,
                end: range.end
            });
        });

        return areaBusy;
    }

    async function buildOccupancySnapshot(date, usableStaff) {
        var assignmentsForDate = [];
        var assignmentToStaff = {};
        var busyRanges = [];
        var staffBusy = {};

        try {
            assignmentsForDate = await fetchAssignmentsForDate(date, usableStaff);
            assignmentToStaff = buildAssignmentStaffBridge(assignmentsForDate);

            var assignmentIds = Object.keys(assignmentToStaff);

            busyRanges = await fetchConfirmedReservationRanges(date, assignmentIds);
            staffBusy = buildStaffBusyMap(usableStaff, assignmentToStaff, busyRanges);
        } catch (error) {
            console.error('[FastAppt] Error fetching confirmed occupancy:', error);
        }

        return {
            assignmentsForDate: assignmentsForDate,
            assignmentToStaff: assignmentToStaff,
            busyRanges: busyRanges,
            staffBusy: staffBusy
        };
    }

    async function buildAreaOccupancySnapshot(date, activeAreas) {
        var assignmentsForDate = [];
        var assignmentToArea = {};
        var busyRanges = [];
        var areaBusy = {};

        try {
            assignmentsForDate = await fetchAssignmentsForDay(date);
            assignmentToArea = buildAssignmentAreaBridge(assignmentsForDate);

            var assignmentIds = Object.keys(assignmentToArea);

            busyRanges = await fetchConfirmedReservationRanges(date, assignmentIds);
            areaBusy = buildAreaBusyMap(activeAreas, assignmentToArea, busyRanges);
        } catch (error) {
            console.error('[FastAppt] Error fetching confirmed area occupancy:', error);
        }

        return {
            assignmentsForDate: assignmentsForDate,
            assignmentToArea: assignmentToArea,
            busyRanges: busyRanges,
            areaBusy: areaBusy
        };
    }

    function getStaffEligibleForService(usableStaff, serviceId) {
        var targetServiceId = String(serviceId || '');

        if (!targetServiceId) {
            return [];
        }

        return usableStaff.filter(function(staff) {
            return Array.isArray(staff.services) && staff.services.some(function(service) {
                return String(service.id) === targetServiceId;
            });
        }).sort(function(a, b) {
            return (a.name || '').localeCompare(b.name || '');
        });
    }

    function isStaffBusyAtSlot(staff, slotValue, slotDuration, staffBusy) {
        var ranges = Array.isArray(staffBusy[staff.id]) ? staffBusy[staff.id] : [];
        var slotStart = timeToMinutes(slotValue);
        var slotEnd = slotStart + slotDuration;

        return ranges.some(function(range) {
            var rangeStart = timeToMinutes(extractTimePart(range.start));
            var rangeEnd = timeToMinutes(extractTimePart(range.end));

            return rangesOverlap(slotStart, slotEnd, rangeStart, rangeEnd);
        });
    }

    function isAreaBusyAtSlot(area, slotValue, slotDuration, areaBusy) {
        var ranges = Array.isArray(areaBusy[area.id]) ? areaBusy[area.id] : [];
        var slotStart = timeToMinutes(slotValue);
        var slotEnd = slotStart + slotDuration;

        return ranges.some(function(range) {
            var rangeStart = timeToMinutes(extractTimePart(range.start));
            var rangeEnd = timeToMinutes(extractTimePart(range.end));

            return rangesOverlap(slotStart, slotEnd, rangeStart, rangeEnd);
        });
    }

    function orderAreasByOccupancy(activeAreas, slotValue, slotDuration, areaBusy) {
        var availableAreas = [];
        var occupiedAreas = [];

        activeAreas.forEach(function(area) {
            var normalizedArea = {
                id: area.id,
                name: area.name || '',
                occupied: isAreaBusyAtSlot(area, slotValue, slotDuration, areaBusy)
            };

            if (normalizedArea.occupied) {
                occupiedAreas.push(normalizedArea);
                return;
            }

            availableAreas.push(normalizedArea);
        });

        availableAreas.sort(function(a, b) {
            return (a.name || '').localeCompare(b.name || '');
        });
        occupiedAreas.sort(function(a, b) {
            return (a.name || '').localeCompare(b.name || '');
        });

        return availableAreas.concat(occupiedAreas);
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

        var occupancySnapshot = null;
        var slotAnalysis = {
            removedSlots: [],
            availableSlots: trimmedSlots.slice()
        };

        occupancySnapshot = await buildOccupancySnapshot(evaluatedDate, usableStaff);
        slotAnalysis = evaluateSlots(trimmedSlots, slotDuration, usableStaff, occupancySnapshot.staffBusy);

        console.log('[FastAppt] Result — slots:', slotAnalysis.availableSlots.length,
            '| removed:', slotAnalysis.removedSlots.length,
            '| busyRanges:', occupancySnapshot.busyRanges.length);

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

    async function getAvailableStaffBySelection(date, time, serviceId, context) {
        var evaluatedDate = date || null;
        var selectedTime = time || null;
        var selectedServiceId = String(serviceId || '');
        var ctx = context || {};
        var slotDuration = 30;
        var usableStaff = normalizeUsableStaff(ctx.usableStaff);
        var eligibleStaff = getStaffEligibleForService(usableStaff, selectedServiceId);

        console.log('[FastAppt] getAvailableStaffBySelection', evaluatedDate,
            '| time:', selectedTime,
            '| service:', selectedServiceId,
            '| eligibleByService:', eligibleStaff.length);

        if (!evaluatedDate || !selectedTime || !selectedServiceId || !eligibleStaff.length) {
            return {
                implemented: true,
                date: evaluatedDate,
                time: selectedTime,
                serviceId: selectedServiceId,
                slotDuration: slotDuration,
                staff: [],
                source: 'confirmed_reservations_filter'
            };
        }

        var occupancySnapshot = await buildOccupancySnapshot(evaluatedDate, eligibleStaff);
        var availableStaff = eligibleStaff.filter(function(staff) {
            return !isStaffBusyAtSlot(staff, selectedTime, slotDuration, occupancySnapshot.staffBusy);
        });

        console.log('[FastAppt] Staff result — available:', availableStaff.length,
            '| removed:', eligibleStaff.length - availableStaff.length,
            '| busyRanges:', occupancySnapshot.busyRanges.length);

        return {
            implemented: true,
            date: evaluatedDate,
            time: selectedTime,
            serviceId: selectedServiceId,
            slotDuration: slotDuration,
            staff: availableStaff,
            source: 'confirmed_reservations_filter'
        };
    }

    async function getAreaAvailabilityBySelection(date, time, staffId, context) {
        var evaluatedDate = date || null;
        var selectedTime = time || null;
        var selectedStaffId = String(staffId || '');
        var ctx = context || {};
        var slotDuration = 30;
        var activeAreas = normalizeActiveServiceAreas(ctx.activeServiceAreas);

        console.log('[FastAppt] getAreaAvailabilityBySelection', evaluatedDate,
            '| time:', selectedTime,
            '| staff:', selectedStaffId,
            '| activeAreas:', activeAreas.length);

        if (!evaluatedDate || !selectedTime || !selectedStaffId || !activeAreas.length) {
            return {
                implemented: true,
                date: evaluatedDate,
                time: selectedTime,
                staffId: selectedStaffId,
                slotDuration: slotDuration,
                areas: [],
                source: 'confirmed_reservations_filter'
            };
        }

        var occupancySnapshot = await buildAreaOccupancySnapshot(evaluatedDate, activeAreas);
        var areas = orderAreasByOccupancy(activeAreas, selectedTime, slotDuration, occupancySnapshot.areaBusy);

        console.log('[FastAppt] Area result — total:', areas.length,
            '| occupied:', areas.filter(function(area) { return area.occupied; }).length,
            '| busyRanges:', occupancySnapshot.busyRanges.length);

        return {
            implemented: true,
            date: evaluatedDate,
            time: selectedTime,
            staffId: selectedStaffId,
            slotDuration: slotDuration,
            areas: areas,
            source: 'confirmed_reservations_filter'
        };
    }

    async function findCompatibleAssignment(date, criteria) {
        var selectedDate = date || null;
        var crit = criteria || {};
        var staffId = String(crit.staffId || '');
        var areaId = String(crit.areaId || '');
        var serviceId = String(crit.serviceId || '');
        var selectedTime = crit.time || '';
        var slotDuration = parseInt(crit.slotDuration, 10) || (window.aa_slot_duration || 60);

        if (!selectedDate || !staffId || !areaId || !serviceId || !selectedTime) {
            return { assignment: null, mode: 'create_new', candidates: [], allDayAssignments: [] };
        }

        var assignments = await fetchAssignmentsForDay(selectedDate);

        console.log('[FastAppointment] Assignments del dia (' + assignments.length + ')');

        var appointmentStart = timeToMinutes(selectedTime);
        var appointmentEnd = appointmentStart + slotDuration;

        var compatible = assignments.filter(function(a) {
            if (String(a.staff_id) !== staffId) return false;
            if (String(a.service_area_id) !== areaId) return false;
            if (a.status !== 'active') return false;

            var hasService = Array.isArray(a.services) && a.services.some(function(s) {
                return String(s.id) === serviceId;
            });
            if (!hasService) return false;

            var aStart = timeToMinutes(a.start_time);
            var aEnd = timeToMinutes(a.end_time);
            if (aStart > appointmentStart || aEnd < appointmentEnd) return false;

            return true;
        });

        console.log('[FastAppointment] Assignments compatibles (' + compatible.length + ')');

        if (compatible.length > 0) {
            console.log('[FastAppointment] Assignment existente reutilizable: ID ' + compatible[0].id);
            return {
                assignment: compatible[0],
                mode: 'existing',
                candidates: compatible,
                allDayAssignments: assignments
            };
        }

        console.log('[FastAppointment] No existe assignment compatible, se debera crear uno nuevo');
        return {
            assignment: null,
            mode: 'create_new',
            candidates: [],
            allDayAssignments: assignments
        };
    }

    async function getAllStaffWithAvailability(date, time, serviceId, context) {
        var evaluatedDate = date || null;
        var selectedTime = time || null;
        var selectedServiceId = String(serviceId || '');
        var ctx = context || {};
        var slotDuration = 30;
        var usableStaff = normalizeUsableStaff(ctx.usableStaff);

        console.log('[FastAppt] getAllStaffWithAvailability', evaluatedDate,
            '| time:', selectedTime,
            '| service:', selectedServiceId,
            '| usableStaff:', usableStaff.length);

        if (!evaluatedDate || !selectedTime || !selectedServiceId || !usableStaff.length) {
            return {
                implemented: true,
                date: evaluatedDate,
                time: selectedTime,
                serviceId: selectedServiceId,
                staff: [],
                source: 'confirmed_reservations_filter'
            };
        }

        var occupancySnapshot = await buildOccupancySnapshot(evaluatedDate, usableStaff);

        var result = usableStaff.map(function(staff) {
            var hasService = Array.isArray(staff.services) && staff.services.some(function(service) {
                return String(service.id) === selectedServiceId;
            });
            var isBusy = isStaffBusyAtSlot(staff, selectedTime, slotDuration, occupancySnapshot.staffBusy);

            var available = hasService && !isBusy;
            var reason = null;
            if (!hasService) {
                reason = 'no_service';
            } else if (isBusy) {
                reason = 'busy';
            }

            return {
                id: staff.id,
                name: staff.name || '',
                available: available,
                reason: reason
            };
        });

        result.sort(function(a, b) {
            if (a.available !== b.available) {
                return a.available ? -1 : 1;
            }
            return (a.name || '').localeCompare(b.name || '');
        });

        console.log('[FastAppt] AllStaff result — total:', result.length,
            '| available:', result.filter(function(s) { return s.available; }).length,
            '| unavailable:', result.filter(function(s) { return !s.available; }).length);

        return {
            implemented: true,
            date: evaluatedDate,
            time: selectedTime,
            serviceId: selectedServiceId,
            slotDuration: slotDuration,
            staff: result,
            source: 'confirmed_reservations_filter'
        };
    }

    window.FastAppointmentTimeAvailabilityService = {
        getAvailabilityByDate: getAvailabilityByDate,
        getAvailableStaffBySelection: getAvailableStaffBySelection,
        getAllStaffWithAvailability: getAllStaffWithAvailability,
        getAreaAvailabilityBySelection: getAreaAvailabilityBySelection,
        findCompatibleAssignment: findCompatibleAssignment
    };
})();

/**
 * Calendar Assignments - Visual overlay layer for assignments on the timeline
 * 
 * Renders semi-transparent blocks showing assignment time ranges,
 * divided horizontally by service_area_id.
 */

(function() {
    'use strict';

    // Color palette for different service areas
    const AREA_COLORS = [
        'rgba(59, 130, 246, 0.15)',   // blue
        'rgba(16, 185, 129, 0.15)',   // green
        'rgba(249, 115, 22, 0.15)',   // orange
        'rgba(139, 92, 246, 0.15)',   // purple
        'rgba(236, 72, 153, 0.15)',   // pink
        'rgba(20, 184, 166, 0.15)',   // teal
        'rgba(245, 158, 11, 0.15)',   // amber
        'rgba(99, 102, 241, 0.15)'    // indigo
    ];

    const AREA_BORDER_COLORS = [
        'rgba(59, 130, 246, 0.4)',    // blue
        'rgba(16, 185, 129, 0.4)',    // green
        'rgba(249, 115, 22, 0.4)',    // orange
        'rgba(139, 92, 246, 0.4)',    // purple
        'rgba(236, 72, 153, 0.4)',    // pink
        'rgba(20, 184, 166, 0.4)',    // teal
        'rgba(245, 158, 11, 0.4)',    // amber
        'rgba(99, 102, 241, 0.4)'     // indigo
    ];

    /**
     * Remove existing assignment overlays from the grid
     */
    function clearOverlays() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;

        const overlays = grid.querySelectorAll('.aa-assignment-overlay');
        overlays.forEach(function(overlay) {
            overlay.remove();
        });
    }

    /**
     * Render assignment overlays on the timeline
     * @param {Array} assignments - Array of assignment objects for the current day
     * @param {Map} slotRowIndex - Map of minutes -> { rowIndex, labelElement }
     */
    function render(assignments, slotRowIndex) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('[CalendarAssignments] Grid #aa-time-grid not found');
            return;
        }

        // Clear existing overlays
        clearOverlays();

        // If no assignments, nothing to render
        if (!assignments || assignments.length === 0) {
            return;
        }

        // 1. Get unique service_area_ids for horizontal division
        const uniqueAreaIds = [];
        assignments.forEach(function(assignment) {
            const areaId = assignment.service_area_id;
            if (areaId && uniqueAreaIds.indexOf(areaId) === -1) {
                uniqueAreaIds.push(areaId);
            }
        });

        // Sort for consistent ordering
        uniqueAreaIds.sort(function(a, b) {
            return parseInt(a) - parseInt(b);
        });

        const totalAreas = uniqueAreaIds.length;
        if (totalAreas === 0) {
            return;
        }

        // 2. Create a map of areaId -> index for positioning
        const areaIndexMap = {};
        uniqueAreaIds.forEach(function(areaId, index) {
            areaIndexMap[areaId] = index;
        });

        // 3. Render each assignment as an overlay
        assignments.forEach(function(assignment) {
            renderAssignmentOverlay(assignment, slotRowIndex, areaIndexMap, totalAreas, grid);
        });
    }

    /**
     * Render a single assignment overlay
     * @param {Object} assignment - Assignment object
     * @param {Map} slotRowIndex - Map of minutes -> { rowIndex }
     * @param {Object} areaIndexMap - Map of service_area_id -> column index
     * @param {number} totalAreas - Total number of unique areas
     * @param {HTMLElement} grid - The grid element
     */
    function renderAssignmentOverlay(assignment, slotRowIndex, areaIndexMap, totalAreas, grid) {
        // Convert start_time and end_time to minutes
        const startMin = window.DateUtils.timeStrToMinutes(assignment.start_time);
        const endMin = window.DateUtils.timeStrToMinutes(assignment.end_time);

        // Normalize interval to slot grid
        const normalized = window.DateUtils.normalizeIntervalToSlotGrid(startMin, endMin, 30);
        if (!normalized) {
            return; // Invalid interval
        }

        // Get start and end row from slotRowIndex
        const startSlotData = slotRowIndex.get(normalized.start);
        const endSlotData = slotRowIndex.get(normalized.end);

        if (!startSlotData) {
            return;
        }

        const startRow = startSlotData.rowIndex;
        // endSlotData might not exist if end is exactly at the last slot boundary
        // In that case, use start + number of slots
        let endRow;
        if (endSlotData) {
            endRow = endSlotData.rowIndex + 1; // +1 because end is inclusive
        } else {
            // Calculate based on duration
            const slots = (normalized.end - normalized.start) / 30;
            endRow = startRow + slots;
        }

        // Calculate horizontal position based on service_area_id
        const areaId = assignment.service_area_id;
        const areaIndex = areaIndexMap[areaId] !== undefined ? areaIndexMap[areaId] : 0;
        const widthPercent = 100 / totalAreas;
        const leftPercent = areaIndex * widthPercent;

        // Get color for this area
        const colorIndex = areaIndex % AREA_COLORS.length;
        const bgColor = AREA_COLORS[colorIndex];
        const borderColor = AREA_BORDER_COLORS[colorIndex];

        // Create overlay element
        const overlay = document.createElement('div');
        overlay.className = 'aa-assignment-overlay';
        overlay.setAttribute('data-assignment-id', assignment.id || '');
        overlay.setAttribute('data-area-id', areaId || '');

        // Position using CSS Grid
        overlay.style.gridColumn = '2';
        overlay.style.gridRow = startRow + ' / ' + endRow;

        // Horizontal positioning within the cell
        overlay.style.position = 'relative';
        overlay.style.marginLeft = leftPercent + '%';
        overlay.style.width = widthPercent + '%';
        overlay.style.boxSizing = 'border-box';

        // Visual styling
        overlay.style.backgroundColor = bgColor;
        overlay.style.borderLeft = '3px solid ' + borderColor;
        overlay.style.borderRadius = '2px';

        // Non-interactive
        overlay.style.pointerEvents = 'none';

        // Z-index below appointment cards
        overlay.style.zIndex = '1';

        // Insert into grid
        grid.appendChild(overlay);
    }

    // Expose public API
    window.CalendarAssignments = {
        render: render,
        clear: clearOverlays
    };

})();

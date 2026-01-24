/**
 * Calendar Assignments - Visual overlay layer for assignments on the timeline
 * 
 * Renders semi-transparent blocks showing assignment time ranges,
 * divided horizontally by service_area_id.
 * Also creates interactive hosts for appointment cards within each overlay.
 */

(function() {
    'use strict';

    // Map interno para guardar referencias: assignmentId -> host element
    var hostsMap = {};

    /**
     * Convert hex color to rgba with specified alpha
     * @param {string} hex - Hex color (e.g., "#71cc30" or "71cc30")
     * @param {number} alpha - Alpha value (0-1)
     * @returns {string} rgba color string
     */
    function hexToRgba(hex, alpha) {
        if (!hex) {
            // Fallback to neutral gray if no color provided
            return 'rgba(107, 114, 128, ' + alpha + ')';
        }
        
        // Remove # if present
        hex = hex.replace('#', '');
        
        // Parse hex to RGB
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    /**
     * Remove existing assignment overlays from the grid
     */
    function clearOverlays() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;

        // Clear assignment overlays
        const overlays = grid.querySelectorAll('.aa-assignment-overlay');
        overlays.forEach(function(overlay) {
            overlay.remove();
        });

        // Clear schedule overlays (horario fijo)
        const scheduleOverlays = grid.querySelectorAll('.aa-schedule-overlay');
        scheduleOverlays.forEach(function(overlay) {
            overlay.remove();
        });

        // Clear cards hosts (new interactive containers)
        const cardsHosts = grid.querySelectorAll('.aa-overlay-cards-host');
        cardsHosts.forEach(function(host) {
            host.remove();
        });

        // Also clear area top borders
        const areaBorders = grid.querySelectorAll('.aa-assignment-area-border');
        areaBorders.forEach(function(border) {
            border.remove();
        });

        // Clear schedule top border
        const scheduleBorders = grid.querySelectorAll('.aa-schedule-area-border');
        scheduleBorders.forEach(function(border) {
            border.remove();
        });

        // Clear hosts map
        hostsMap = {};
    }

    /**
     * Render assignment overlays on the timeline
     * @param {Array} assignments - Array of assignment objects for the current day
     * @param {Map} slotRowIndex - Map of minutes -> { rowIndex, labelElement }
     * @param {Array} scheduleIntervals - Array of schedule intervals for the day (optional)
     */
    function render(assignments, slotRowIndex, scheduleIntervals) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('[CalendarAssignments] Grid #aa-time-grid not found');
            return;
        }

        // Clear existing overlays
        clearOverlays();

        // Check if we have schedule intervals (horario fijo)
        const hasSchedule = scheduleIntervals && scheduleIntervals.length > 0;
        
        // If no assignments and no schedule, nothing to render
        if ((!assignments || assignments.length === 0) && !hasSchedule) {
            return;
        }

        // 1. Get unique service_area_ids for horizontal division
        const uniqueAreaIds = [];
        if (assignments && assignments.length > 0) {
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
        }

        // 2. Calculate total columns: schedule (if exists) + assignments areas
        // Schedule takes the first column (index 0), assignments start at index 1 (or 0 if no schedule)
        const scheduleColumnIndex = hasSchedule ? 0 : -1; // -1 means no schedule column
        const assignmentStartIndex = hasSchedule ? 1 : 0;
        const totalColumns = (hasSchedule ? 1 : 0) + uniqueAreaIds.length;

        // If no columns at all, nothing to render
        if (totalColumns === 0) {
            return;
        }

        // 3. Create a map of areaId -> column index for positioning
        // Assignments start after the schedule column (if it exists)
        const areaIndexMap = {};
        uniqueAreaIds.forEach(function(areaId, index) {
            areaIndexMap[areaId] = assignmentStartIndex + index;
        });

        // 4. Render schedule overlay (horario fijo) if it exists
        if (hasSchedule) {
            renderScheduleOverlay(scheduleIntervals, slotRowIndex, scheduleColumnIndex, totalColumns, grid);
        }

        // 5. Render each assignment as an overlay
        if (assignments && assignments.length > 0) {
            assignments.forEach(function(assignment) {
                renderAssignmentOverlay(assignment, slotRowIndex, areaIndexMap, totalColumns, grid);
            });
        }

        // 6. Render area top borders (one per unique service_area_name, plus schedule if exists)
        renderAreaTopBorders(assignments, areaIndexMap, totalColumns, grid, hasSchedule, scheduleColumnIndex);
    }

    /**
     * Render schedule overlay (horario fijo)
     * @param {Array} scheduleIntervals - Array of {start, end} in minutes
     * @param {Map} slotRowIndex - Map of minutes -> { rowIndex }
     * @param {number} columnIndex - Column index for horizontal positioning
     * @param {number} totalColumns - Total number of columns
     * @param {HTMLElement} grid - The grid element
     */
    function renderScheduleOverlay(scheduleIntervals, slotRowIndex, columnIndex, totalColumns, grid) {
        if (!scheduleIntervals || scheduleIntervals.length === 0) {
            return;
        }

        // Color azul desaturado para el horario fijo
        const scheduleColor = '107, 142, 185'; // Azul desaturado (RGB)
        const bgColor = 'rgba(' + scheduleColor + ', 0.12)';
        const borderColor = 'rgba(' + scheduleColor + ', 0.35)';

        // Calculate horizontal position
        const widthPercent = 100 / totalColumns;
        const leftPercent = columnIndex * widthPercent;

        // Render each schedule interval as an overlay
        scheduleIntervals.forEach(function(interval) {
            // Get start and end row from slotRowIndex
            const startSlotData = slotRowIndex.get(interval.start);
            
            if (!startSlotData) {
                return;
            }

            const startRow = startSlotData.rowIndex;
            
            // Find end row
            let endRow;
            const endSlotData = slotRowIndex.get(interval.end - 30); // -30 because end is exclusive
            if (endSlotData) {
                endRow = endSlotData.rowIndex + 1;
            } else {
                // Calculate based on duration
                const slots = (interval.end - interval.start) / 30;
                endRow = startRow + slots;
            }

            // Create overlay element
            const overlay = document.createElement('div');
            overlay.className = 'aa-schedule-overlay';
            overlay.setAttribute('data-schedule-interval', interval.start + '-' + interval.end);

            // Position using CSS Grid
            overlay.style.gridColumn = '2';
            overlay.style.gridRow = startRow + ' / ' + endRow;

            // Horizontal positioning within the cell
            overlay.style.position = 'relative';
            overlay.style.marginLeft = leftPercent + '%';
            overlay.style.width = widthPercent + '%';
            overlay.style.boxSizing = 'border-box';

            // Visual styling - azul desaturado
            overlay.style.backgroundColor = bgColor;
            overlay.style.borderLeft = '3px solid ' + borderColor;
            overlay.style.borderTop = '3px solid ' + borderColor;
            overlay.style.borderTopLeftRadius = '10px';
            overlay.style.borderTopRightRadius = '10px';
            overlay.style.borderBottomLeftRadius = '2px';
            overlay.style.borderBottomRightRadius = '2px';

            // Non-interactive
            overlay.style.pointerEvents = 'none';

            // Z-index below appointment cards but same as assignment overlays
            overlay.style.zIndex = '1';

            // Insert overlay into grid (without label - label goes to host for clickability)
            grid.appendChild(overlay);

            // ===== CREATE INTERACTIVE HOST FOR FIXED APPOINTMENT CARDS =====
            const host = document.createElement('div');
            host.className = 'aa-overlay-cards-host';
            
            // Dataset attributes for matching fixed citas
            host.setAttribute('data-type', 'schedule');
            host.setAttribute('data-start-row', startRow);
            host.setAttribute('data-end-row', endRow);
            host.setAttribute('data-start-min', interval.start);
            host.setAttribute('data-end-min', interval.end);

            // Position using CSS Grid (same as overlay)
            host.style.gridColumn = '2';
            host.style.gridRow = startRow + ' / ' + endRow;

            // Horizontal positioning (same as overlay)
            host.style.position = 'relative';
            host.style.marginLeft = leftPercent + '%';
            host.style.width = widthPercent + '%';
            host.style.boxSizing = 'border-box';

            // Transparent background, interactive
            host.style.background = 'transparent';
            host.style.pointerEvents = 'auto';
            host.style.zIndex = '5';
            host.style.overflow = 'hidden'; // Prevent cards from overflowing host bounds

            // CSS Grid for internal card layout
            host.style.display = 'grid';
            host.style.gridTemplateColumns = 'minmax(0, 1fr)'; // Allow column to shrink below content size

            // Calculate rowHeight from the first .aa-time-content element
            const firstContent = grid.querySelector('.aa-time-content');
            let rowHeight = 40; // fallback
            if (firstContent) {
                const rect = firstContent.getBoundingClientRect();
                if (rect.height > 0) {
                    rowHeight = rect.height;
                }
            }
            host.style.gridAutoRows = rowHeight + 'px';

            // Insert host into grid (after overlay, so it's on top)
            grid.appendChild(host);

            // ===== ADD CLICKABLE LABEL TO HOST (not overlay) =====
            const fixedStaffName = window.AA_CALENDAR_DATA?.fixedStaffName || '';
            const fixedServiceName = window.AA_CALENDAR_DATA?.fixedServiceName || '';
            let scheduleLabelText = 'Horario fijo'; // fallback
            
            if (fixedStaffName && fixedServiceName) {
                scheduleLabelText = fixedStaffName + ' - ' + fixedServiceName;
            } else if (fixedStaffName) {
                scheduleLabelText = fixedStaffName;
            } else if (fixedServiceName) {
                scheduleLabelText = fixedServiceName;
            }
            
            const scheduleLabel = document.createElement('div');
            scheduleLabel.className = 'aa-schedule-label';
            scheduleLabel.textContent = scheduleLabelText;
            
            // Add popover data attributes for click interaction
            scheduleLabel.setAttribute('data-aa-popover', '1');
            scheduleLabel.setAttribute('data-aa-popover-staff', fixedStaffName);
            scheduleLabel.setAttribute('data-aa-popover-services', fixedServiceName);
            
            // Style the label - positioned at top of host
            scheduleLabel.style.position = 'absolute';
            scheduleLabel.style.top = '0';
            scheduleLabel.style.left = '0';
            scheduleLabel.style.right = '0';
            scheduleLabel.style.color = '#ffffff';
            scheduleLabel.style.fontSize = '11px';
            scheduleLabel.style.fontWeight = '600';
            scheduleLabel.style.padding = '3px 6px';
            scheduleLabel.style.borderTopLeftRadius = '10px';
            scheduleLabel.style.borderTopRightRadius = '10px';
            scheduleLabel.style.backgroundColor = borderColor;
            scheduleLabel.style.boxSizing = 'border-box';
            scheduleLabel.style.overflow = 'hidden';
            scheduleLabel.style.textOverflow = 'ellipsis';
            scheduleLabel.style.whiteSpace = 'nowrap';
            scheduleLabel.style.lineHeight = '1.2';
            scheduleLabel.style.zIndex = '25'; // High z-index within host
            scheduleLabel.style.cursor = 'pointer';
            scheduleLabel.style.pointerEvents = 'auto';
            
            host.appendChild(scheduleLabel);

            // Save reference in hostsMap with special key
            hostsMap['schedule_' + interval.start + '_' + interval.end] = host;
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
            endRow = endSlotData.rowIndex; // end is exclusive boundary
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

        // Get color from service area configuration
        const baseColor = assignment.service_area_color || null;
        const bgColor = hexToRgba(baseColor, 0.15);
        const borderColor = hexToRgba(baseColor, 0.4);

        // Create overlay element (visual, non-interactive)
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
        overlay.style.borderTop = '3px solid ' + borderColor;
        overlay.style.borderTopLeftRadius = '10px';
        overlay.style.borderTopRightRadius = '10px';
        overlay.style.borderBottomLeftRadius = '2px';
        overlay.style.borderBottomRightRadius = '2px';

        // Non-interactive
        overlay.style.pointerEvents = 'none';

        // Z-index below appointment cards
        overlay.style.zIndex = '1';

        // Insert overlay into grid (without label - label goes to host for clickability)
        grid.appendChild(overlay);
        console.log('✅ Overlay insertado en grid con gridRow:', overlay.style.gridRow);

        // ===== CREATE INTERACTIVE HOST FOR APPOINTMENT CARDS =====
        const host = document.createElement('div');
        host.className = 'aa-overlay-cards-host';
        
        // Dataset attributes for matching citas
        host.setAttribute('data-assignment-id', assignment.id || '');
        host.setAttribute('data-area-id', areaId || '');
        host.setAttribute('data-staff-id', assignment.staff_id || '');
        host.setAttribute('data-start-row', startRow);
        host.setAttribute('data-end-row', endRow);
        host.setAttribute('data-start-min', startMin);
        host.setAttribute('data-end-min', endMin);

        // Position using CSS Grid (same as overlay)
        host.style.gridColumn = '2';
        host.style.gridRow = startRow + ' / ' + endRow;
        console.log('✅ Host creado con gridRow:', host.style.gridRow, 'y atributos data:', {
            'data-assignment-id': host.getAttribute('data-assignment-id'),
            'data-start-row': host.getAttribute('data-start-row'),
            'data-end-row': host.getAttribute('data-end-row'),
            'data-start-min': host.getAttribute('data-start-min'),
            'data-end-min': host.getAttribute('data-end-min')
        });

        // Horizontal positioning (same as overlay)
        host.style.position = 'relative';
        host.style.marginLeft = leftPercent + '%';
        host.style.width = widthPercent + '%';
        host.style.boxSizing = 'border-box';

        // Transparent background, interactive
        host.style.background = 'transparent';
        host.style.pointerEvents = 'auto';
        host.style.zIndex = '5';
        host.style.overflow = 'hidden'; // Prevent cards from overflowing host bounds

        // CSS Grid for internal card layout
        host.style.display = 'grid';
        host.style.gridTemplateColumns = 'minmax(0, 1fr)'; // Allow column to shrink below content size

        // Calculate rowHeight from the first .aa-time-content element
        const firstContent = grid.querySelector('.aa-time-content');
        let rowHeight = 40; // fallback
        if (firstContent) {
            const rect = firstContent.getBoundingClientRect();
            if (rect.height > 0) {
                rowHeight = rect.height;
            }
        }
        host.style.gridAutoRows = rowHeight + 'px';

        // Insert host into grid (after overlay, so it's on top)
        grid.appendChild(host);

        // ===== ADD CLICKABLE LABEL TO HOST (not overlay) =====
        if (assignment.staff_name) {
            const staffLabel = document.createElement('div');
            staffLabel.className = 'aa-assignment-staff-label';
            
            // Build label text: "{staff_name} - {services}" or just staff name
            let labelText = assignment.staff_name;
            
            // Check if assignment has services (service_names array from backend)
            const servicesStr = (assignment.service_names && assignment.service_names.length > 0) 
                ? assignment.service_names.join(', ') 
                : '';
            
            if (servicesStr) {
                labelText = assignment.staff_name + ' - ' + servicesStr;
            }
            
            staffLabel.textContent = labelText;
            
            // Add popover data attributes for click interaction
            staffLabel.setAttribute('data-aa-popover', '1');
            staffLabel.setAttribute('data-aa-popover-staff', assignment.staff_name || '');
            staffLabel.setAttribute('data-aa-popover-services', servicesStr);
            
            // Style the staff label - positioned at top of host
            staffLabel.style.position = 'absolute';
            staffLabel.style.top = '0';
            staffLabel.style.left = '0';
            staffLabel.style.right = '0';
            staffLabel.style.color = '#ffffff';
            staffLabel.style.fontSize = '11px';
            staffLabel.style.fontWeight = '600';
            staffLabel.style.padding = '3px 6px';
            staffLabel.style.borderTopLeftRadius = '10px';
            staffLabel.style.borderTopRightRadius = '10px';
            staffLabel.style.backgroundColor = borderColor;
            staffLabel.style.boxSizing = 'border-box';
            staffLabel.style.overflow = 'hidden';
            staffLabel.style.textOverflow = 'ellipsis';
            staffLabel.style.whiteSpace = 'nowrap';
            staffLabel.style.lineHeight = '1.2';
            staffLabel.style.zIndex = '25'; // High z-index within host
            staffLabel.style.cursor = 'pointer';
            staffLabel.style.pointerEvents = 'auto';
            
            host.appendChild(staffLabel);
        }

        // Save reference in the hosts map
        if (assignment.id) {
            hostsMap[assignment.id] = host;
        }
    }

    /**
     * Render top borders for each unique service area name
     * These borders are positioned over the grid's border-top (15px)
     * Only spans the width of column 2 (aa-time-content), not column 1 (aa-time-label)
     * @param {Array} assignments - Array of assignment objects
     * @param {Object} areaIndexMap - Map of service_area_id -> column index
     * @param {number} totalColumns - Total number of columns (including schedule if exists)
     * @param {HTMLElement} grid - The grid element
     * @param {boolean} hasSchedule - Whether there's a schedule overlay
     * @param {number} scheduleColumnIndex - Column index for the schedule (0 if exists, -1 if not)
     */
    function renderAreaTopBorders(assignments, areaIndexMap, totalColumns, grid, hasSchedule, scheduleColumnIndex) {
        // Calculate the width of column 2 (content) relative to the grid
        // Grid has: column 1 (auto width for labels) + column 2 (1fr for content)
        const gridRect = grid.getBoundingClientRect();
        const gridWidth = gridRect.width;
        
        // Find the first content element to get its position
        const firstContent = grid.querySelector('.aa-time-content');
        if (!firstContent) {
            return; // No content column found
        }
        
        const contentRect = firstContent.getBoundingClientRect();
        const gridRectLeft = gridRect.left;
        const contentLeft = contentRect.left;
        
        // Calculate offset: distance from grid left edge to content left edge
        const contentOffset = contentLeft - gridRectLeft;
        const contentWidth = contentRect.width;
        
        // Calculate percentages relative to grid width
        const contentLeftPercent = (contentOffset / gridWidth) * 100;
        const contentWidthPercent = (contentWidth / gridWidth) * 100;

        // Ensure grid is positioned for absolute children
        grid.style.position = 'relative';

        // 1. Render schedule top border if it exists
        if (hasSchedule && scheduleColumnIndex >= 0) {
            const scheduleColor = '107, 142, 185'; // Azul desaturado (RGB)
            const scheduleBorderColor = 'rgba(' + scheduleColor + ', 1.0)';
            
            // Calculate width and position
            const scheduleWidthPercent = contentWidthPercent / totalColumns;
            const scheduleLeftPercent = contentLeftPercent + (scheduleColumnIndex * scheduleWidthPercent);

            // Build label text for schedule border (same logic as schedule overlay)
            const fixedStaffNameBorder = window.AA_CALENDAR_DATA?.fixedStaffName || '';
            const fixedServiceNameBorder = window.AA_CALENDAR_DATA?.fixedServiceName || '';
            let scheduleBorderLabelText = 'Horario fijo'; // fallback
            
            if (fixedStaffNameBorder && fixedServiceNameBorder) {
                scheduleBorderLabelText = fixedStaffNameBorder + ' - ' + fixedServiceNameBorder;
            } else if (fixedStaffNameBorder) {
                scheduleBorderLabelText = fixedStaffNameBorder;
            } else if (fixedServiceNameBorder) {
                scheduleBorderLabelText = fixedServiceNameBorder;
            }
            
            // Create schedule border element
            const scheduleBorder = document.createElement('div');
            scheduleBorder.className = 'aa-schedule-area-border';
            scheduleBorder.setAttribute('data-area-name', scheduleBorderLabelText);

            // Position absolutely over the grid's border-top (15px height)
            scheduleBorder.style.position = 'absolute';
            scheduleBorder.style.top = '-15px';
            scheduleBorder.style.left = scheduleLeftPercent + '%';
            scheduleBorder.style.width = scheduleWidthPercent + '%';
            scheduleBorder.style.height = '15px';
            scheduleBorder.style.backgroundColor = scheduleBorderColor;
            scheduleBorder.style.borderRadius = '0';
            scheduleBorder.style.boxSizing = 'border-box';
            scheduleBorder.style.display = 'flex';
            scheduleBorder.style.alignItems = 'center';
            scheduleBorder.style.justifyContent = 'center';
            scheduleBorder.style.zIndex = '10';

            // Add label text
            const scheduleLabelSpan = document.createElement('span');
            scheduleLabelSpan.textContent = scheduleBorderLabelText;
            scheduleLabelSpan.style.color = '#ffffff';
            scheduleLabelSpan.style.fontSize = '11px';
            scheduleLabelSpan.style.fontWeight = '600';
            scheduleLabelSpan.style.whiteSpace = 'nowrap';
            scheduleLabelSpan.style.overflow = 'hidden';
            scheduleLabelSpan.style.textOverflow = 'ellipsis';
            scheduleLabelSpan.style.padding = '0 6px';
            scheduleLabelSpan.style.lineHeight = '1.2';

            scheduleBorder.appendChild(scheduleLabelSpan);
            grid.appendChild(scheduleBorder);
        }

        // 2. Render assignment area borders (if there are assignments)
        if (!assignments || assignments.length === 0) {
            return;
        }

        // Group assignments by service_area_name
        const assignmentsByAreaName = {};
        assignments.forEach(function(assignment) {
            const areaName = assignment.service_area_name || 'Sin área';
            if (!assignmentsByAreaName[areaName]) {
                assignmentsByAreaName[areaName] = [];
            }
            assignmentsByAreaName[areaName].push(assignment);
        });

        // For each unique area name, create one border
        Object.keys(assignmentsByAreaName).forEach(function(areaName) {
            const areaAssignments = assignmentsByAreaName[areaName];
            if (areaAssignments.length === 0) return;

            // Get the first assignment to extract color and area info
            const firstAssignment = areaAssignments[0];
            const areaId = firstAssignment.service_area_id;
            const areaIndex = areaIndexMap[areaId] !== undefined ? areaIndexMap[areaId] : 0;
            
            // Calculate width and position as percentage of content column only
            const areaWidthPercent = contentWidthPercent / totalColumns;
            const areaLeftPercent = contentLeftPercent + (areaIndex * areaWidthPercent);

            // Get color from service area configuration
            const baseColor = firstAssignment.service_area_color || null;
            const borderColor = hexToRgba(baseColor, 1.0); // Full opacity for top border

            // Create area border element
            const areaBorder = document.createElement('div');
            areaBorder.className = 'aa-assignment-area-border';
            areaBorder.setAttribute('data-area-name', areaName);
            areaBorder.setAttribute('data-area-id', areaId || '');

            // Position absolutely over the grid's border-top (15px height)
            // The grid has a 15px border-top, so we position at -15px to overlay it
            // Position only over column 2 (content), not column 1 (labels)
            areaBorder.style.position = 'absolute';
            areaBorder.style.top = '-15px';
            areaBorder.style.left = areaLeftPercent + '%';
            areaBorder.style.width = areaWidthPercent + '%';
            areaBorder.style.height = '15px';
            areaBorder.style.backgroundColor = borderColor;
            areaBorder.style.borderRadius = '0'; // No rounded corners
            areaBorder.style.boxSizing = 'border-box';
            areaBorder.style.display = 'flex';
            areaBorder.style.alignItems = 'center';
            areaBorder.style.justifyContent = 'center';
            areaBorder.style.zIndex = '10'; // Above the grid border

            // Add area name text
            const areaNameText = document.createElement('span');
            areaNameText.textContent = areaName;
            areaNameText.style.color = '#ffffff';
            areaNameText.style.fontSize = '11px';
            areaNameText.style.fontWeight = '600';
            areaNameText.style.whiteSpace = 'nowrap';
            areaNameText.style.overflow = 'hidden';
            areaNameText.style.textOverflow = 'ellipsis';
            areaNameText.style.padding = '0 6px';
            areaNameText.style.lineHeight = '1.2';

            areaBorder.appendChild(areaNameText);

            // Insert into grid (positioned relative to grid)
            grid.appendChild(areaBorder);
        });
    }

    /**
     * Find the appropriate cards host for a given appointment
     * @param {Object} cita - Appointment object (may have assignment_id, staff_id, area_id)
     * @param {Object} posicion - Position data with slotInicio (minutes) and bloquesOcupados
     * @returns {HTMLElement|null} - The host element or null if not found
     */
    function getCardsHostForCita(cita, posicion) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return null;

        // 1. If cita has assignment_id, try direct match
        if (cita.assignment_id && hostsMap[cita.assignment_id]) {
            return hostsMap[cita.assignment_id];
        }

        // Calculate cita's time range in minutes
        const citaStartMin = posicion ? posicion.slotInicio : null;
        const citaDuration = posicion ? (posicion.bloquesOcupados * 30) : 0;
        const citaEndMin = citaStartMin !== null ? (citaStartMin + citaDuration) : null;

        // 2. CITAS FIXED: assignment_id === null AND (servicio starts with "fixed::" OR no staff_id)
        const isFixedCita = !cita.assignment_id && (
            (cita.servicio && typeof cita.servicio === 'string' && cita.servicio.startsWith('fixed::')) ||
            (!cita.assignment_id && !cita.staff_id)
        );
        
        if (isFixedCita && citaStartMin !== null) {
            // Find schedule host where cita's time falls within
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host[data-type="schedule"]');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostStartMin = parseInt(host.getAttribute('data-start-min'), 10);
                const hostEndMin = parseInt(host.getAttribute('data-end-min'), 10);
                
                // Cita start must be within host's range (start >= hostStart AND start < hostEnd)
                if (citaStartMin >= hostStartMin && citaStartMin < hostEndMin) {
                    return host;
                }
            }
        }

        // 3. If cita has staff_id, find host where staff matches AND time overlaps
        if (cita.staff_id) {
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostStaffId = host.getAttribute('data-staff-id');
                
                if (hostStaffId && hostStaffId == cita.staff_id) {
                    // Check if cita falls within host's time range
                    if (citaStartMin !== null) {
                        const hostStartMin = parseInt(host.getAttribute('data-start-min'), 10);
                        const hostEndMin = parseInt(host.getAttribute('data-end-min'), 10);
                        
                        // Cita must be within host's range (start >= hostStart AND end <= hostEnd)
                        if (citaStartMin >= hostStartMin && citaEndMin <= hostEndMin) {
                            return host;
                        }
                    } else {
                        // No position data, return first match by staff
                        return host;
                    }
                }
            }
        }

        // 4. Fallback: try to match by area_id
        if (cita.area_id || cita.service_area_id) {
            const areaId = cita.area_id || cita.service_area_id;
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostAreaId = host.getAttribute('data-area-id');
                
                if (hostAreaId && hostAreaId == areaId) {
                    // Check time range if available
                    if (citaStartMin !== null) {
                        const hostStartMin = parseInt(host.getAttribute('data-start-min'), 10);
                        const hostEndMin = parseInt(host.getAttribute('data-end-min'), 10);
                        
                        if (citaStartMin >= hostStartMin && citaEndMin <= hostEndMin) {
                            return host;
                        }
                    } else {
                        return host;
                    }
                }
            }
        }

        // 5. No match found
        return null;
    }

    /**
     * Clear all cards from hosts (empty their content)
     * Preserves labels (.aa-schedule-label and .aa-assignment-staff-label)
     */
    function clearHosts() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
        hosts.forEach(function(host) {
            // Find and remove only appointment cards, preserving labels
            const cards = host.querySelectorAll('.aa-appointment-card');
            cards.forEach(function(card) {
                card.remove();
            });
        });
    }

    // Expose public API
    window.CalendarAssignments = {
        render: render,
        clear: clearOverlays,
        getCardsHostForCita: getCardsHostForCita,
        clearHosts: clearHosts
    };

})();

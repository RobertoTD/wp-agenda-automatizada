/**
 * Calendar Assignments - Visual overlay layer for assignments on the timeline
 * 
 * Renders semi-transparent blocks showing assignment time ranges,
 * divided horizontally by service_area_id.
 */

(function() {
    'use strict';

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

            // Add label at the top
            const scheduleLabel = document.createElement('div');
            scheduleLabel.className = 'aa-schedule-label';
            scheduleLabel.textContent = 'Horario fijo';
            
            // Style the label
            scheduleLabel.style.position = 'absolute';
            scheduleLabel.style.top = '0';
            scheduleLabel.style.left = '0';
            scheduleLabel.style.right = '0';
            scheduleLabel.style.color = '#ffffff';
            scheduleLabel.style.fontSize = '11px';
            scheduleLabel.style.fontWeight = '600';
            scheduleLabel.style.padding = '4px 6px';
            scheduleLabel.style.borderTopLeftRadius = '10px';
            scheduleLabel.style.borderTopRightRadius = '10px';
            scheduleLabel.style.backgroundColor = borderColor;
            scheduleLabel.style.boxSizing = 'border-box';
            scheduleLabel.style.overflow = 'hidden';
            scheduleLabel.style.textOverflow = 'ellipsis';
            scheduleLabel.style.whiteSpace = 'nowrap';
            scheduleLabel.style.lineHeight = '1.2';
            scheduleLabel.style.zIndex = '2';
            
            overlay.appendChild(scheduleLabel);

            // Insert into grid
            grid.appendChild(overlay);
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

        // Get color from service area configuration
        const baseColor = assignment.service_area_color || null;
        const bgColor = hexToRgba(baseColor, 0.15);
        const borderColor = hexToRgba(baseColor, 0.4);

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
        overlay.style.borderTop = '3px solid ' + borderColor;
        overlay.style.borderTopLeftRadius = '10px';
        overlay.style.borderTopRightRadius = '10px';
        overlay.style.borderBottomLeftRadius = '2px';
        overlay.style.borderBottomRightRadius = '2px';

        // Non-interactive
        overlay.style.pointerEvents = 'none';

        // Z-index below appointment cards
        overlay.style.zIndex = '1';

        // Add staff name label at the top
        if (assignment.staff_name) {
            const staffLabel = document.createElement('div');
            staffLabel.className = 'aa-assignment-staff-label';
            staffLabel.textContent = assignment.staff_name;
            
            // Style the staff label
            staffLabel.style.position = 'absolute';
            staffLabel.style.top = '0';
            staffLabel.style.left = '0';
            staffLabel.style.right = '0';
            staffLabel.style.color = '#ffffff';
            staffLabel.style.fontSize = '11px';
            staffLabel.style.fontWeight = '600';
            staffLabel.style.padding = '4px 6px';
            staffLabel.style.borderTopLeftRadius = '10px';
            staffLabel.style.borderTopRightRadius = '10px';
            staffLabel.style.backgroundColor = borderColor;
            staffLabel.style.boxSizing = 'border-box';
            staffLabel.style.overflow = 'hidden';
            staffLabel.style.textOverflow = 'ellipsis';
            staffLabel.style.whiteSpace = 'nowrap';
            staffLabel.style.lineHeight = '1.2';
            staffLabel.style.zIndex = '2';
            
            overlay.appendChild(staffLabel);
        }

        // Insert into grid
        grid.appendChild(overlay);
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

            // Create schedule border element
            const scheduleBorder = document.createElement('div');
            scheduleBorder.className = 'aa-schedule-area-border';
            scheduleBorder.setAttribute('data-area-name', 'Horario fijo');

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
            const scheduleLabelText = document.createElement('span');
            scheduleLabelText.textContent = 'Horario fijo';
            scheduleLabelText.style.color = '#ffffff';
            scheduleLabelText.style.fontSize = '11px';
            scheduleLabelText.style.fontWeight = '600';
            scheduleLabelText.style.whiteSpace = 'nowrap';
            scheduleLabelText.style.overflow = 'hidden';
            scheduleLabelText.style.textOverflow = 'ellipsis';
            scheduleLabelText.style.padding = '0 6px';
            scheduleLabelText.style.lineHeight = '1.2';

            scheduleBorder.appendChild(scheduleLabelText);
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

    // Expose public API
    window.CalendarAssignments = {
        render: render,
        clear: clearOverlays
    };

})();

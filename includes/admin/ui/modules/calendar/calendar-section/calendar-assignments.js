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

        const overlays = grid.querySelectorAll('.aa-assignment-overlay');
        overlays.forEach(function(overlay) {
            overlay.remove();
        });

        // Also clear area top borders
        const areaBorders = grid.querySelectorAll('.aa-assignment-area-border');
        areaBorders.forEach(function(border) {
            border.remove();
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

        // 4. Render area top borders (one per unique service_area_name)
        renderAreaTopBorders(assignments, areaIndexMap, totalAreas, grid);
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
     * @param {number} totalAreas - Total number of unique areas
     * @param {HTMLElement} grid - The grid element
     */
    function renderAreaTopBorders(assignments, areaIndexMap, totalAreas, grid) {
        if (!assignments || assignments.length === 0) {
            return;
        }

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
            const areaWidthPercent = contentWidthPercent / totalAreas;
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
            grid.style.position = 'relative'; // Ensure grid is positioned for absolute children
            grid.appendChild(areaBorder);
        });
    }

    // Expose public API
    window.CalendarAssignments = {
        render: render,
        clear: clearOverlays
    };

})();

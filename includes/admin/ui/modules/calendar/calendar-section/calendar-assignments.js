/**
 * Calendar Assignments - Visual overlay layer for assignments on the timeline
 * 
 * Design System: See /docs/DESIGN_BRIEF.md
 * - Overlays muy sutiles (alpha < 0.08)
 * - Headers con fondo neutro y borde inferior
 * - Prioridad visual: Citas > Estados > Áreas
 */

(function() {
    'use strict';

    // =============================================
    // DESIGN TOKENS
    // =============================================
    const TOKENS = {
        gray50: '#f9fafb',
        gray100: '#f3f4f6',
        gray200: '#e5e7eb',
        gray500: '#6b7280',
        gray600: '#4b5563',
        gray700: '#374151',
        
        radiusMd: '6px',
        
        space2: '6px',
        space3: '8px',
        
        textXs: '11px',
        
        transitionFast: '150ms ease'
    };

    // Map interno para guardar referencias: assignmentId -> host element
    var hostsMap = {};

    /**
     * Convert hex color to rgba with specified alpha
     */
    function hexToRgba(hex, alpha) {
        if (!hex) {
            return 'rgba(107, 114, 128, ' + alpha + ')';
        }
        hex = hex.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    /**
     * Get darker version of hex color for text
     */
    function hexToDarker(hex) {
        if (!hex) return TOKENS.gray600;
        hex = hex.replace('#', '');
        const r = Math.max(0, parseInt(hex.substring(0, 2), 16) - 60);
        const g = Math.max(0, parseInt(hex.substring(2, 4), 16) - 60);
        const b = Math.max(0, parseInt(hex.substring(4, 6), 16) - 60);
        return 'rgb(' + r + ', ' + g + ', ' + b + ')';
    }

    /**
     * Remove existing assignment overlays from the grid
     */
    function clearOverlays() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;

        const overlays = grid.querySelectorAll('.aa-assignment-overlay');
        overlays.forEach(function(overlay) { overlay.remove(); });

        const scheduleOverlays = grid.querySelectorAll('.aa-schedule-overlay');
        scheduleOverlays.forEach(function(overlay) { overlay.remove(); });

        const cardsHosts = grid.querySelectorAll('.aa-overlay-cards-host');
        cardsHosts.forEach(function(host) { host.remove(); });

        const areaBorders = grid.querySelectorAll('.aa-assignment-area-border');
        areaBorders.forEach(function(border) { border.remove(); });

        const scheduleBorders = grid.querySelectorAll('.aa-schedule-area-border');
        scheduleBorders.forEach(function(border) { border.remove(); });

        hostsMap = {};
    }

    /**
     * Render assignment overlays on the timeline
     */
    function render(assignments, slotRowIndex, scheduleIntervals) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('[CalendarAssignments] Grid #aa-time-grid not found');
            return;
        }

        clearOverlays();

        const hasSchedule = scheduleIntervals && scheduleIntervals.length > 0;
        
        if ((!assignments || assignments.length === 0) && !hasSchedule) {
            return;
        }

        // Get unique service_area_ids
        const uniqueAreaIds = [];
        if (assignments && assignments.length > 0) {
            assignments.forEach(function(assignment) {
                const areaId = assignment.service_area_id;
                if (areaId && uniqueAreaIds.indexOf(areaId) === -1) {
                    uniqueAreaIds.push(areaId);
                }
            });
            uniqueAreaIds.sort(function(a, b) {
                return parseInt(a) - parseInt(b);
            });
        }

        const scheduleColumnIndex = hasSchedule ? 0 : -1;
        const assignmentStartIndex = hasSchedule ? 1 : 0;
        const totalColumns = (hasSchedule ? 1 : 0) + uniqueAreaIds.length;

        if (totalColumns === 0) {
            return;
        }

        const areaIndexMap = {};
        uniqueAreaIds.forEach(function(areaId, index) {
            areaIndexMap[areaId] = assignmentStartIndex + index;
        });

        if (hasSchedule) {
            renderScheduleOverlay(scheduleIntervals, slotRowIndex, scheduleColumnIndex, totalColumns, grid);
        }

        if (assignments && assignments.length > 0) {
            assignments.forEach(function(assignment) {
                renderAssignmentOverlay(assignment, slotRowIndex, areaIndexMap, totalColumns, grid);
            });
        }

        renderAreaTopBorders(assignments, areaIndexMap, totalColumns, grid, hasSchedule, scheduleColumnIndex);
    }

    /**
     * Render schedule overlay (horario fijo)
     */
    function renderScheduleOverlay(scheduleIntervals, slotRowIndex, columnIndex, totalColumns, grid) {
        if (!scheduleIntervals || scheduleIntervals.length === 0) {
            return;
        }

        // Color azul desaturado muy sutil
        const scheduleColorRgb = '107, 142, 185';
        const bgColor = 'rgba(' + scheduleColorRgb + ', 0.04)'; // Muy sutil
        const headerBgColor = 'rgba(' + scheduleColorRgb + ', 0.12)';
        const borderColor = 'rgba(' + scheduleColorRgb + ', 0.25)';
        const textColor = 'rgb(70, 100, 140)';

        const widthPercent = 100 / totalColumns;
        const leftPercent = columnIndex * widthPercent;

        scheduleIntervals.forEach(function(interval) {
            const startSlotData = slotRowIndex.get(interval.start);
            if (!startSlotData) return;

            const startRow = startSlotData.rowIndex;
            
            let endRow;
            const endSlotData = slotRowIndex.get(interval.end - 30);
            if (endSlotData) {
                endRow = endSlotData.rowIndex + 1;
            } else {
                const slots = (interval.end - interval.start) / 30;
                endRow = startRow + slots;
            }

            // ===== OVERLAY (Visual background - muy sutil) =====
            const overlay = document.createElement('div');
            overlay.className = 'aa-schedule-overlay';
            overlay.setAttribute('data-schedule-interval', interval.start + '-' + interval.end);

            Object.assign(overlay.style, {
                gridColumn: '2',
                gridRow: startRow + ' / ' + endRow,
                position: 'relative',
                marginLeft: leftPercent + '%',
                width: widthPercent + '%',
                boxSizing: 'border-box',
                backgroundColor: bgColor,
                borderTopLeftRadius: TOKENS.radiusMd,
                borderTopRightRadius: TOKENS.radiusMd,
                borderBottomLeftRadius: '2px',
                borderBottomRightRadius: '2px',
                pointerEvents: 'none',
                zIndex: '1'
            });

            grid.appendChild(overlay);

            // ===== HOST (Interactive container) =====
            const host = document.createElement('div');
            host.className = 'aa-overlay-cards-host';
            host.setAttribute('data-type', 'schedule');
            host.setAttribute('data-start-row', startRow);
            host.setAttribute('data-end-row', endRow);
            host.setAttribute('data-start-min', interval.start);
            host.setAttribute('data-end-min', interval.end);

            Object.assign(host.style, {
                gridColumn: '2',
                gridRow: startRow + ' / ' + endRow,
                position: 'relative',
                marginLeft: leftPercent + '%',
                width: widthPercent + '%',
                boxSizing: 'border-box',
                background: 'transparent',
                pointerEvents: 'auto',
                zIndex: '5',
                overflow: 'hidden',
                display: 'grid',
                gridTemplateColumns: 'minmax(0, 1fr)',
                gridAutoRows: '40px'
            });

            grid.appendChild(host);

            // ===== LABEL (Header estilo dashboard) =====
            const fixedStaffName = window.AA_CALENDAR_DATA?.fixedStaffName || '';
            const fixedServiceName = window.AA_CALENDAR_DATA?.fixedServiceName || '';
            let scheduleLabelText = 'Horario fijo';
            
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
            
            scheduleLabel.setAttribute('data-aa-popover', '1');
            scheduleLabel.setAttribute('data-aa-popover-staff', fixedStaffName);
            scheduleLabel.setAttribute('data-aa-popover-services', fixedServiceName);
            
            Object.assign(scheduleLabel.style, {
                position: 'absolute',
                top: '0',
                left: '0',
                right: '0',
                color: textColor,
                fontSize: TOKENS.textXs,
                fontWeight: '600',
                padding: `${TOKENS.space2} ${TOKENS.space3}`,
                borderTopLeftRadius: TOKENS.radiusMd,
                borderTopRightRadius: TOKENS.radiusMd,
                backgroundColor: headerBgColor,
                borderBottom: `2px solid ${borderColor}`,
                boxSizing: 'border-box',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
                lineHeight: '1.3',
                zIndex: '25',
                cursor: 'pointer',
                pointerEvents: 'auto',
                transition: `background-color ${TOKENS.transitionFast}`
            });
            
            scheduleLabel.addEventListener('mouseenter', function() {
                scheduleLabel.style.backgroundColor = 'rgba(' + scheduleColorRgb + ', 0.18)';
            });
            scheduleLabel.addEventListener('mouseleave', function() {
                scheduleLabel.style.backgroundColor = headerBgColor;
            });
            
            host.appendChild(scheduleLabel);
            hostsMap['schedule_' + interval.start + '_' + interval.end] = host;
        });
    }

    /**
     * Render a single assignment overlay
     */
    function renderAssignmentOverlay(assignment, slotRowIndex, areaIndexMap, totalAreas, grid) {
        const startMin = window.DateUtils.timeStrToMinutes(assignment.start_time);
        const endMin = window.DateUtils.timeStrToMinutes(assignment.end_time);

        const normalized = window.DateUtils.normalizeIntervalToSlotGrid(startMin, endMin, 30);
        if (!normalized) return;

        const startSlotData = slotRowIndex.get(normalized.start);
        const endSlotData = slotRowIndex.get(normalized.end);

        if (!startSlotData) return;

        const startRow = startSlotData.rowIndex;
        let endRow;
        if (endSlotData) {
            endRow = endSlotData.rowIndex;
        } else {
            const slots = (normalized.end - normalized.start) / 30;
            endRow = startRow + slots;
        }

        const areaId = assignment.service_area_id;
        const areaIndex = areaIndexMap[areaId] !== undefined ? areaIndexMap[areaId] : 0;
        const widthPercent = 100 / totalAreas;
        const leftPercent = areaIndex * widthPercent;

        // Colores muy sutiles para el overlay
        const baseColor = assignment.service_area_color || null;
        const bgColor = hexToRgba(baseColor, 0.04); // Muy sutil
        const headerBgColor = hexToRgba(baseColor, 0.12);
        const borderColor = hexToRgba(baseColor, 0.3);
        const textColor = hexToDarker(baseColor);

        // ===== OVERLAY (Visual background - muy sutil) =====
        const overlay = document.createElement('div');
        overlay.className = 'aa-assignment-overlay';
        overlay.setAttribute('data-assignment-id', assignment.id || '');
        overlay.setAttribute('data-area-id', areaId || '');

        Object.assign(overlay.style, {
            gridColumn: '2',
            gridRow: startRow + ' / ' + endRow,
            position: 'relative',
            marginLeft: leftPercent + '%',
            width: widthPercent + '%',
            boxSizing: 'border-box',
            backgroundColor: bgColor,
            borderTopLeftRadius: TOKENS.radiusMd,
            borderTopRightRadius: TOKENS.radiusMd,
            borderBottomLeftRadius: '2px',
            borderBottomRightRadius: '2px',
            pointerEvents: 'none',
            zIndex: '1'
        });

        grid.appendChild(overlay);

        // ===== HOST (Interactive container) =====
        const host = document.createElement('div');
        host.className = 'aa-overlay-cards-host';
        host.setAttribute('data-assignment-id', assignment.id || '');
        host.setAttribute('data-area-id', areaId || '');
        host.setAttribute('data-staff-id', assignment.staff_id || '');
        host.setAttribute('data-start-row', startRow);
        host.setAttribute('data-end-row', endRow);
        host.setAttribute('data-start-min', startMin);
        host.setAttribute('data-end-min', endMin);

        Object.assign(host.style, {
            gridColumn: '2',
            gridRow: startRow + ' / ' + endRow,
            position: 'relative',
            marginLeft: leftPercent + '%',
            width: widthPercent + '%',
            boxSizing: 'border-box',
            background: 'transparent',
            pointerEvents: 'auto',
            zIndex: '5',
            overflow: 'hidden',
            display: 'grid',
            gridTemplateColumns: 'minmax(0, 1fr)',
            gridAutoRows: '40px'
        });

        console.log('✅ Host creado con gridRow:', host.style.gridRow, 'y atributos data:', {
            'data-assignment-id': host.getAttribute('data-assignment-id'),
            'data-start-row': host.getAttribute('data-start-row'),
            'data-end-row': host.getAttribute('data-end-row'),
            'data-start-min': host.getAttribute('data-start-min'),
            'data-end-min': host.getAttribute('data-end-min')
        });

        grid.appendChild(host);

        // ===== LABEL (Header estilo dashboard) =====
        if (assignment.staff_name) {
            const staffLabel = document.createElement('div');
            staffLabel.className = 'aa-assignment-staff-label';
            
            let labelText = assignment.staff_name;
            const servicesStr = (assignment.service_names && assignment.service_names.length > 0) 
                ? assignment.service_names.join(', ') 
                : '';
            
            if (servicesStr) {
                labelText = assignment.staff_name + ' - ' + servicesStr;
            }
            
            staffLabel.textContent = labelText;
            
            staffLabel.setAttribute('data-aa-popover', '1');
            staffLabel.setAttribute('data-aa-popover-staff', assignment.staff_name || '');
            staffLabel.setAttribute('data-aa-popover-services', servicesStr);
            
            Object.assign(staffLabel.style, {
                position: 'absolute',
                top: '0',
                left: '0',
                right: '0',
                color: textColor,
                fontSize: TOKENS.textXs,
                fontWeight: '600',
                padding: `${TOKENS.space2} ${TOKENS.space3}`,
                borderTopLeftRadius: TOKENS.radiusMd,
                borderTopRightRadius: TOKENS.radiusMd,
                backgroundColor: headerBgColor,
                borderBottom: `2px solid ${borderColor}`,
                boxSizing: 'border-box',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
                lineHeight: '1.3',
                zIndex: '45',
                cursor: 'pointer',
                pointerEvents: 'auto',
                transition: `background-color ${TOKENS.transitionFast}`
            });
            
            // Hover effect
            const originalBg = headerBgColor;
            const hoverBg = hexToRgba(baseColor, 0.18);
            
            staffLabel.addEventListener('mouseenter', function() {
                staffLabel.style.backgroundColor = hoverBg;
            });
            staffLabel.addEventListener('mouseleave', function() {
                staffLabel.style.backgroundColor = originalBg;
            });
            
            host.appendChild(staffLabel);
        }

        if (assignment.id) {
            hostsMap[assignment.id] = host;
        }
    }

    /**
     * Render top borders for each unique service area name
     */
    function renderAreaTopBorders(assignments, areaIndexMap, totalColumns, grid, hasSchedule, scheduleColumnIndex) {
        const gridRect = grid.getBoundingClientRect();
        const gridWidth = gridRect.width;
        
        const firstContent = grid.querySelector('.aa-time-content');
        if (!firstContent) return;
        
        const contentRect = firstContent.getBoundingClientRect();
        const gridRectLeft = gridRect.left;
        const contentLeft = contentRect.left;
        
        const contentOffset = contentLeft - gridRectLeft;
        const contentWidth = contentRect.width;
        
        const contentLeftPercent = (contentOffset / gridWidth) * 100;
        const contentWidthPercent = (contentWidth / gridWidth) * 100;

        grid.style.position = 'relative';

        // Schedule top border
        if (hasSchedule && scheduleColumnIndex >= 0) {
            const scheduleColorRgb = '107, 142, 185';
            const scheduleBorderColor = 'rgb(70, 100, 140)';
            
            const scheduleWidthPercent = contentWidthPercent / totalColumns;
            const scheduleLeftPercent = contentLeftPercent + (scheduleColumnIndex * scheduleWidthPercent);

            const fixedStaffNameBorder = window.AA_CALENDAR_DATA?.fixedStaffName || '';
            const fixedServiceNameBorder = window.AA_CALENDAR_DATA?.fixedServiceName || '';
            let scheduleBorderLabelText = 'Horario fijo';
            
            if (fixedStaffNameBorder && fixedServiceNameBorder) {
                scheduleBorderLabelText = fixedStaffNameBorder + ' - ' + fixedServiceNameBorder;
            } else if (fixedStaffNameBorder) {
                scheduleBorderLabelText = fixedStaffNameBorder;
            } else if (fixedServiceNameBorder) {
                scheduleBorderLabelText = fixedServiceNameBorder;
            }
            
            const scheduleBorder = document.createElement('div');
            scheduleBorder.className = 'aa-schedule-area-border';
            scheduleBorder.setAttribute('data-area-name', scheduleBorderLabelText);

            Object.assign(scheduleBorder.style, {
                position: 'absolute',
                top: '-15px',
                left: scheduleLeftPercent + '%',
                width: scheduleWidthPercent + '%',
                height: '15px',
                backgroundColor: TOKENS.gray50,
                borderRadius: '0',
                boxSizing: 'border-box',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: '10',
                borderBottom: `2px solid rgba(${scheduleColorRgb}, 0.3)`
            });

            const scheduleLabelSpan = document.createElement('span');
            scheduleLabelSpan.textContent = scheduleBorderLabelText;
            Object.assign(scheduleLabelSpan.style, {
                color: scheduleBorderColor,
                fontSize: TOKENS.textXs,
                fontWeight: '600',
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                padding: '0 6px',
                lineHeight: '1.2'
            });

            scheduleBorder.appendChild(scheduleLabelSpan);
            grid.appendChild(scheduleBorder);
        }

        // Assignment area borders
        if (!assignments || assignments.length === 0) return;

        const assignmentsByAreaName = {};
        assignments.forEach(function(assignment) {
            const areaName = assignment.service_area_name || 'Sin área';
            if (!assignmentsByAreaName[areaName]) {
                assignmentsByAreaName[areaName] = [];
            }
            assignmentsByAreaName[areaName].push(assignment);
        });

        Object.keys(assignmentsByAreaName).forEach(function(areaName) {
            const areaAssignments = assignmentsByAreaName[areaName];
            if (areaAssignments.length === 0) return;

            const firstAssignment = areaAssignments[0];
            const areaId = firstAssignment.service_area_id;
            const areaIndex = areaIndexMap[areaId] !== undefined ? areaIndexMap[areaId] : 0;
            
            const areaWidthPercent = contentWidthPercent / totalColumns;
            const areaLeftPercent = contentLeftPercent + (areaIndex * areaWidthPercent);

            const baseColor = firstAssignment.service_area_color || null;
            const borderColor = hexToRgba(baseColor, 0.3);
            const textColor = hexToDarker(baseColor);

            const areaBorder = document.createElement('div');
            areaBorder.className = 'aa-assignment-area-border';
            areaBorder.setAttribute('data-area-name', areaName);
            areaBorder.setAttribute('data-area-id', areaId || '');

            Object.assign(areaBorder.style, {
                position: 'absolute',
                top: '-15px',
                left: areaLeftPercent + '%',
                width: areaWidthPercent + '%',
                height: '15px',
                backgroundColor: TOKENS.gray50,
                borderRadius: '0',
                boxSizing: 'border-box',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: '10',
                borderBottom: `2px solid ${borderColor}`
            });

            const areaNameText = document.createElement('span');
            areaNameText.textContent = areaName;
            Object.assign(areaNameText.style, {
                color: textColor,
                fontSize: TOKENS.textXs,
                fontWeight: '600',
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                padding: '0 6px',
                lineHeight: '1.2'
            });

            areaBorder.appendChild(areaNameText);
            grid.appendChild(areaBorder);
        });
    }

    /**
     * Find the appropriate cards host for a given appointment
     */
    function getCardsHostForCita(cita, posicion) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return null;

        if (cita.assignment_id && hostsMap[cita.assignment_id]) {
            return hostsMap[cita.assignment_id];
        }

        const citaStartMin = posicion ? posicion.slotInicio : null;
        const citaDuration = posicion ? (posicion.bloquesOcupados * 30) : 0;
        const citaEndMin = citaStartMin !== null ? (citaStartMin + citaDuration) : null;

        const isFixedCita = !cita.assignment_id;
        
        if (isFixedCita && citaStartMin !== null) {
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host[data-type="schedule"]');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostStartMin = parseInt(host.getAttribute('data-start-min'), 10);
                const hostEndMin = parseInt(host.getAttribute('data-end-min'), 10);
                
                if (citaStartMin >= hostStartMin && citaStartMin < hostEndMin) {
                    return host;
                }
            }
        }

        if (cita.staff_id) {
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostStaffId = host.getAttribute('data-staff-id');
                
                if (hostStaffId && hostStaffId == cita.staff_id) {
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

        if (cita.area_id || cita.service_area_id) {
            const areaId = cita.area_id || cita.service_area_id;
            const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
            for (let i = 0; i < hosts.length; i++) {
                const host = hosts[i];
                const hostAreaId = host.getAttribute('data-area-id');
                
                if (hostAreaId && hostAreaId == areaId) {
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

        return null;
    }

    /**
     * Clear all cards from hosts
     */
    function clearHosts() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
        hosts.forEach(function(host) {
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

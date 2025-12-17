/**
 * Calendar Overlap - Overlap detection and horizontal split calculation
 */

(function() {
    'use strict';

    /**
     * Detectar si dos rangos se solapan
     * @param {number} start1 - Inicio del rango 1
     * @param {number} end1 - Fin del rango 1 (exclusivo)
     * @param {number} start2 - Inicio del rango 2
     * @param {number} end2 - Fin del rango 2 (exclusivo)
     * @returns {boolean} - true si se solapan
     */
    function rangosSeSolapan(start1, end1, start2, end2) {
        return start1 < end2 && start2 < end1;
    }

    /**
     * Calcular solapamientos y asignar índices para split horizontal
     * @param {Array} citas - Array de objetos con { id, startRow, bloquesOcupados }
     * @returns {Object} - Mapa de id -> { overlapIndex, overlapCount }
     */
    function computeOverlaps(citas) {
        if (!citas || citas.length === 0) {
            return {};
        }

        // Mapa resultado: id -> { overlapIndex, overlapCount }
        const resultado = {};

        // Encontrar grupos de citas que se solapan (componentes conectados)
        const grupos = [];
        const procesadas = new Set();

        citas.forEach((cita, index) => {
            if (procesadas.has(index)) return;

            // Encontrar todas las citas que se solapan con esta (transitivamente)
            const grupo = [];
            const porProcesar = [index];

            while (porProcesar.length > 0) {
                const idxActual = porProcesar.pop();
                if (procesadas.has(idxActual)) continue;
                
                procesadas.add(idxActual);
                const citaActual = citas[idxActual];
                grupo.push(citaActual);

                const startRow = citaActual.startRow;
                const endRow = startRow + citaActual.bloquesOcupados;

                // Buscar todas las citas que se solapan con esta
                citas.forEach((otraCita, otroIndex) => {
                    if (procesadas.has(otroIndex)) return;
                    
                    const otraStartRow = otraCita.startRow;
                    const otraEndRow = otraStartRow + otraCita.bloquesOcupados;
                    
                    // Verificar si se solapan
                    if (rangosSeSolapan(startRow, endRow, otraStartRow, otraEndRow)) {
                        porProcesar.push(otroIndex);
                    }
                });
            }

            if (grupo.length > 0) {
                grupos.push(grupo);
            }
        });

        // Asignar índices a cada grupo
        grupos.forEach(grupo => {
            // Ordenar por startRow para asignar índices consistentes
            grupo.sort((a, b) => a.startRow - b.startRow);
            
            const overlapCount = grupo.length;
            
            // Asignar overlapIndex y overlapCount a todas las citas del grupo
            grupo.forEach((cita, index) => {
                resultado[cita.id] = {
                    overlapIndex: index,
                    overlapCount: overlapCount
                };
            });
        });

        return resultado;
    }

    // Exponer API pública
    window.CalendarOverlap = {
        computeOverlaps: computeOverlaps
    };

})();


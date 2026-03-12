(function() {
    'use strict';

    function getAjaxUrl() {
        if (window.wpaa_vars && window.wpaa_vars.ajax_url) {
            return window.wpaa_vars.ajax_url;
        }

        if (window.ajaxurl) {
            return window.ajaxurl;
        }

        throw new Error('AJAX URL no disponible para Fast Appointment');
    }

    async function requestJson(action, options) {
        const config = options || {};
        const url = new URL(getAjaxUrl(), window.location.origin);
        const formData = new FormData();

        formData.append('action', action);

        if (config.queryParams) {
            Object.keys(config.queryParams).forEach(function(key) {
                if (typeof config.queryParams[key] !== 'undefined' && config.queryParams[key] !== null) {
                    url.searchParams.set(key, String(config.queryParams[key]));
                }
            });
        }

        if (config.body) {
            Object.keys(config.body).forEach(function(key) {
                if (typeof config.body[key] !== 'undefined' && config.body[key] !== null) {
                    formData.append(key, String(config.body[key]));
                }
            });
        }

        const response = await fetch(url.toString(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Error HTTP ' + response.status + ' en ' + action);
        }

        const result = await response.json();

        if (!result || result.success !== true) {
            const message = result && result.data && result.data.message
                ? result.data.message
                : 'Respuesta invalida en ' + action;
            throw new Error(message);
        }

        return result.data || {};
    }

    function buildMessages(summary) {
        const messages = [];

        if (!summary.hasAreas) {
            messages.push('Fast Appointment requiere al menos una zona de atencion activa.');
        }

        if (!summary.hasServices) {
            messages.push('Fast Appointment requiere al menos un servicio activo.');
        }

        if (!summary.hasUsableStaff) {
            messages.push('Fast Appointment requiere al menos un staff activo con al menos un servicio asignado.');
        }

        if (!messages.length) {
            messages.push('Los prerrequisitos del sistema estan completos para continuar con Fast Appointment.');
        }

        return messages;
    }

    async function fetchActiveServiceAreas() {
        const data = await requestJson('aa_get_service_areas', {
            queryParams: {
                only_active: 'true'
            }
        });

        const serviceAreas = Array.isArray(data.service_areas) ? data.service_areas : [];

        return serviceAreas.filter(function(area) {
            return parseInt(area.active, 10) === 1 || typeof area.active === 'undefined';
        });
    }

    async function fetchActiveServices() {
        const data = await requestJson('aa_get_services_db');
        const services = Array.isArray(data.services) ? data.services : [];

        return services.filter(function(service) {
            return parseInt(service.active, 10) === 1;
        });
    }

    async function fetchActiveStaff() {
        const data = await requestJson('aa_get_staff', {
            queryParams: {
                only_active: 'true'
            }
        });

        const staff = Array.isArray(data.staff) ? data.staff : [];

        return staff.filter(function(member) {
            return parseInt(member.active, 10) === 1 || typeof member.active === 'undefined';
        });
    }

    async function fetchStaffServices(staffId) {
        const data = await requestJson('aa_get_staff_services', {
            body: {
                staff_id: staffId
            }
        });

        return Array.isArray(data.selected) ? data.selected : [];
    }

    async function fetchUsableStaff(activeStaff) {
        if (!Array.isArray(activeStaff) || !activeStaff.length) {
            return [];
        }

        const results = await Promise.all(activeStaff.map(async function(member) {
            const services = await fetchStaffServices(member.id);

            return {
                id: member.id,
                name: member.name || '',
                active: member.active,
                services: services,
                serviceCount: services.length
            };
        }));

        return results.filter(function(member) {
            return member.serviceCount > 0;
        });
    }

    async function evaluatePrerequisites() {
        try {
            const serviceAreasPromise = fetchActiveServiceAreas();
            const servicesPromise = fetchActiveServices();
            const activeStaffPromise = fetchActiveStaff();

            const results = await Promise.all([
                serviceAreasPromise,
                servicesPromise,
                activeStaffPromise
            ]);

            const serviceAreas = results[0];
            const services = results[1];
            const activeStaff = results[2];
            const usableStaff = await fetchUsableStaff(activeStaff);

            const summary = {
                hasServices: services.length > 0,
                hasUsableStaff: usableStaff.length > 0,
                hasAreas: serviceAreas.length > 0,
                canStart: false,
                messages: [],
                counts: {
                    services: services.length,
                    activeStaff: activeStaff.length,
                    usableStaff: usableStaff.length,
                    areas: serviceAreas.length
                },
                usableStaff: usableStaff,
                activeServices: services,
                checkedAt: new Date().toISOString()
            };

            summary.canStart = summary.hasServices && summary.hasUsableStaff && summary.hasAreas;
            summary.messages = buildMessages(summary);

            return summary;
        } catch (error) {
            console.error('[FastAppointmentPrerequisitesService] Error evaluando prerrequisitos:', error);

            return {
                hasServices: false,
                hasUsableStaff: false,
                hasAreas: false,
                canStart: false,
                messages: ['No se pudieron validar los prerrequisitos de Fast Appointment.'],
                counts: {
                    services: 0,
                    activeStaff: 0,
                    usableStaff: 0,
                    areas: 0
                },
                usableStaff: [],
                activeServices: [],
                checkedAt: new Date().toISOString(),
                error: error && error.message ? error.message : 'Unknown error'
            };
        }
    }

    window.FastAppointmentPrerequisitesService = {
        evaluate: evaluatePrerequisites,
        fetchActiveServiceAreas: fetchActiveServiceAreas,
        fetchActiveServices: fetchActiveServices,
        fetchActiveStaff: fetchActiveStaff,
        fetchUsableStaff: fetchUsableStaff,
        fetchStaffServices: fetchStaffServices
    };
})();

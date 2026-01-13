/**
 * Services Section - Services Management
 * 
 * Handles UI logic for managing services
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let servicesRoot = null;

    /**
     * Initialize the services section
     */
    function initServicesSection() {
        servicesRoot = document.getElementById('aa-services-root');
        
        // Fail safely if root doesn't exist
        if (!servicesRoot) {
            console.warn('[Services Section] Root element #aa-services-root not found');
            return;
        }

        // Render services
        renderServices(servicesRoot);
    }

    /**
     * Render services list
     * @param {HTMLElement} root - The root container element
     */
    function renderServices(root) {
        // Placeholder content for now
        root.innerHTML = '<p class="text-sm text-gray-500">No hay servicios registrados.</p>';
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServicesSection);
    } else {
        // DOM already ready
        initServicesSection();
    }

})();

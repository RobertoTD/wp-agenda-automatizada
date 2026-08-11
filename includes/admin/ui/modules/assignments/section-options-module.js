/**
 * Section Options Module — menú contextual ⋮ en headers de secciones del módulo Servicios.
 * Misma dinámica que list-options-module (toggle, dismiss, placement fixed).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isBound = false;
    var openSectionId = '';
    var isViewportDismissBound = false;

    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function setVisible(el, visible) {
        if (!el) {
            return;
        }

        if (visible) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }

    function findMenuForSection(sectionId) {
        if (!sectionId) {
            return null;
        }

        return document.querySelector('.aa-module-section-options-menu[data-section-id="' + sectionId + '"]');
    }

    function findTriggerForSection(sectionId) {
        if (!sectionId) {
            return null;
        }

        return document.querySelector('.aa-module-section-options-trigger[data-section-id="' + sectionId + '"]');
    }

    function setTriggerExpanded(sectionId, expanded) {
        var trigger = findTriggerForSection(sectionId);

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function getMenuPlacement() {
        return globalRoot.AAExecutableOptionsMenuPlacement || null;
    }

    function resetMenuPlacement(menu) {
        var placement = getMenuPlacement();

        if (placement && typeof placement.resetOptionsMenuPlacement === 'function') {
            placement.resetOptionsMenuPlacement(menu);
            return;
        }

        if (!menu) {
            return;
        }

        menu.classList.remove('bottom-full', 'mb-2');
        menu.classList.add('top-full', 'mt-2');
        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
        menu.style.zIndex = '';
    }

    function clearSectionCardMenuElevation() {
        document.querySelectorAll('.aa-module-section-card--floating-menu').forEach(function (card) {
            card.classList.remove('aa-module-section-card--floating-menu');
        });
    }

    function setSectionCardMenuElevation(menu, elevated) {
        var sectionCard = menu && menu.closest
            ? menu.closest('details.aa-module-section-card')
            : null;

        if (!sectionCard) {
            return;
        }

        if (elevated) {
            sectionCard.classList.add('aa-module-section-card--floating-menu');
        } else {
            sectionCard.classList.remove('aa-module-section-card--floating-menu');
        }
    }

    function positionSectionMenu(menu, sectionId) {
        if (!menu) {
            return;
        }

        var trigger = findTriggerForSection(sectionId);
        var placement = getMenuPlacement();

        if (!trigger || !placement || typeof placement.positionOptionsMenu !== 'function') {
            return;
        }

        placement.positionOptionsMenu(menu, trigger);
    }

    function closeAllMenus() {
        document.querySelectorAll('.aa-module-section-options-menu').forEach(function (menu) {
            resetMenuPlacement(menu);
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-module-section-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        clearSectionCardMenuElevation();
        openSectionId = '';
    }

    function openMenu(sectionId) {
        var menu = findMenuForSection(sectionId);

        if (!menu) {
            return;
        }

        closeAllMenus();
        setVisible(menu, true);
        positionSectionMenu(menu, sectionId);
        setSectionCardMenuElevation(menu, true);
        setTriggerExpanded(sectionId, true);
        openSectionId = sectionId;
    }

    function toggleMenu(sectionId) {
        if (openSectionId === sectionId) {
            closeAllMenus();
            return;
        }

        openMenu(sectionId);
    }

    function isInsideSectionOptions(target) {
        if (!target || !target.closest) {
            return false;
        }

        return !!target.closest('.aa-module-section-options');
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var trigger = target && target.closest
            ? target.closest('[data-aa-section-options-trigger]')
            : null;

        if (trigger && !trigger.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            toggleMenu(asString(trigger.getAttribute('data-section-id')).trim());
            return;
        }

        var menuItem = target && target.closest
            ? target.closest('.aa-module-section-options-menu [role="menuitem"]')
            : null;

        if (menuItem) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            var action = asString(menuItem.getAttribute('data-aa-section-action')).trim();
            var sectionId = asString(menuItem.getAttribute('data-section-id')).trim();

            closeAllMenus();

            if (action === 'new') {
                openCreateModalForSection(sectionId);
            }

            return;
        }

        if (openSectionId !== '' && !isInsideSectionOptions(target)) {
            closeAllMenus();
        }
    }

    /**
     * Opens the transversal create modal for the given section id.
     * @param {string} sectionId areas | staff | services
     */
    function openCreateModalForSection(sectionId) {
        var AAAdmin = globalRoot.AAAdmin;

        if (sectionId === 'areas') {
            if (AAAdmin && AAAdmin.AreaCreateModal
                && typeof AAAdmin.AreaCreateModal.openCreate === 'function') {
                AAAdmin.AreaCreateModal.openCreate();
                return;
            }
            console.error('[SectionOptions] AAAdmin.AreaCreateModal.openCreate no disponible');
            return;
        }

        if (sectionId === 'staff') {
            if (AAAdmin && AAAdmin.StaffCreateModal
                && typeof AAAdmin.StaffCreateModal.openCreate === 'function') {
                AAAdmin.StaffCreateModal.openCreate();
                return;
            }
            console.error('[SectionOptions] AAAdmin.StaffCreateModal.openCreate no disponible');
            return;
        }

        if (sectionId === 'services') {
            if (AAAdmin && AAAdmin.ServiceCreateModal
                && typeof AAAdmin.ServiceCreateModal.openCreate === 'function') {
                AAAdmin.ServiceCreateModal.openCreate();
                return;
            }
            console.error('[SectionOptions] AAAdmin.ServiceCreateModal.openCreate no disponible');
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || openSectionId === '') {
            return;
        }

        closeAllMenus();
    }

    function handleSectionToggle(event) {
        var details = event.target;

        if (!details || !details.classList || !details.classList.contains('aa-module-section-card')) {
            return;
        }

        if (!details.open && openSectionId !== '') {
            var sectionId = asString(details.getAttribute('data-aa-section')).trim();

            if (sectionId && sectionId === openSectionId) {
                closeAllMenus();
            }
        }
    }

    function handleViewportChange() {
        if (openSectionId !== '') {
            closeAllMenus();
        }
    }

    function bindViewportDismissHandlers() {
        if (isViewportDismissBound) {
            return;
        }

        isViewportDismissBound = true;
        globalRoot.addEventListener('scroll', handleViewportChange, true);
        globalRoot.addEventListener('resize', handleViewportChange);
    }

    function bind() {
        if (isBound) {
            return;
        }

        if (!document.querySelector('.aa-module-section-options-trigger')) {
            return;
        }

        isBound = true;
        // Capture: true — igual que list-options-module. Los triggers llevan
        // onclick="event.stopPropagation()" para no togglear el <details>;
        // en bubble el listener de document nunca vería el click.
        document.addEventListener('click', handleDocumentClick, true);
        document.addEventListener('keydown', handleDocumentKeydown);
        document.addEventListener('toggle', handleSectionToggle, true);
        bindViewportDismissHandlers();
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            bind: bind,
            closeAllMenus: closeAllMenus,
            openMenu: openMenu,
            toggleMenu: toggleMenu
        };
    }
})();

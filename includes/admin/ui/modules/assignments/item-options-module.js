/**
 * Item Options Module — menú contextual ⋮ en cards de zona, personal y servicio.
 * Misma dinámica que section-options-module / task-options-module.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isBound = false;
    var isViewportDismissBound = false;
    var openItemKey = '';

    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function itemKey(type, id) {
        return asString(type).trim() + ':' + asString(id).trim();
    }

    function parseItemKey(key) {
        var parts = asString(key).split(':');
        return {
            type: asString(parts[0]).trim(),
            id: asString(parts.slice(1).join(':')).trim()
        };
    }

    function itemMeta(type) {
        if (type === 'area') {
            return { title: 'Opciones de zona', editLabel: 'Editar' };
        }
        if (type === 'staff') {
            return { title: 'Opciones de personal', editLabel: 'Editar' };
        }
        return { title: 'Opciones de servicio', editLabel: 'Editar' };
    }

    /**
     * Markup del menú ⋮ para una card de assignments.
     * @param {'area'|'staff'|'service'} type
     * @param {number|string} id
     * @returns {string}
     */
    function renderAssignmentItemOptions(type, id) {
        var safeType = escapeHtml(asString(type).trim());
        var safeId = escapeHtml(asString(id).trim());
        var meta = itemMeta(safeType);

        return ''
            + '<div class="relative aa-assignment-item-options shrink-0 ml-auto">'
            + '<button type="button"'
            + ' data-aa-item-options-trigger="1"'
            + ' data-aa-item-type="' + safeType + '"'
            + ' data-aa-item-id="' + safeId + '"'
            + ' onclick="event.stopPropagation()"'
            + ' title="' + escapeHtml(meta.title) + '"'
            + ' aria-label="' + escapeHtml(meta.title) + '"'
            + ' aria-haspopup="menu"'
            + ' aria-expanded="false"'
            + ' class="aa-assignment-item-options-trigger aa-options-trigger-flat">'
            + '<svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="5" cy="12" r="1.75"/>'
            + '<circle cx="12" cy="12" r="1.75"/>'
            + '<circle cx="19" cy="12" r="1.75"/>'
            + '</svg>'
            + '</button>'
            + '<div class="hidden aa-assignment-item-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"'
            + ' role="menu"'
            + ' data-aa-item-type="' + safeType + '"'
            + ' data-aa-item-id="' + safeId + '">'
            + '<button type="button" role="menuitem"'
            + ' data-aa-item-action="edit"'
            + ' data-aa-item-type="' + safeType + '"'
            + ' data-aa-item-id="' + safeId + '"'
            + ' onclick="event.stopPropagation()"'
            + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
            + escapeHtml(meta.editLabel)
            + '</button>'
            + '</div>'
            + '</div>';
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

    function findMenu(type, id) {
        if (!type || !id) {
            return null;
        }

        return document.querySelector(
            '.aa-assignment-item-options-menu[data-aa-item-type="' + type + '"][data-aa-item-id="' + id + '"]'
        );
    }

    function findTrigger(type, id) {
        if (!type || !id) {
            return null;
        }

        return document.querySelector(
            '.aa-assignment-item-options-trigger[data-aa-item-type="' + type + '"][data-aa-item-id="' + id + '"]'
        );
    }

    function setTriggerExpanded(type, id, expanded) {
        var trigger = findTrigger(type, id);

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

    function positionItemMenu(menu, type, id) {
        if (!menu) {
            return;
        }

        var trigger = findTrigger(type, id);
        var placement = getMenuPlacement();

        if (!trigger || !placement || typeof placement.positionOptionsMenu !== 'function') {
            return;
        }

        placement.positionOptionsMenu(menu, trigger);
    }

    function closeAllMenus() {
        document.querySelectorAll('.aa-assignment-item-options-menu').forEach(function (menu) {
            resetMenuPlacement(menu);
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-assignment-item-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        clearSectionCardMenuElevation();
        openItemKey = '';
    }

    function openMenu(type, id) {
        var menu = findMenu(type, id);

        if (!menu) {
            return;
        }

        closeAllMenus();
        setVisible(menu, true);
        positionItemMenu(menu, type, id);
        setSectionCardMenuElevation(menu, true);
        setTriggerExpanded(type, id, true);
        openItemKey = itemKey(type, id);
    }

    function toggleMenu(type, id) {
        if (openItemKey === itemKey(type, id)) {
            closeAllMenus();
            return;
        }

        openMenu(type, id);
    }

    function isInsideItemOptions(target) {
        if (!target || !target.closest) {
            return false;
        }

        return !!target.closest('.aa-assignment-item-options');
    }

    function openEditModal(type, id) {
        var AAAdmin = globalRoot.AAAdmin;
        var numericId = parseInt(id, 10);

        if (!(numericId > 0)) {
            console.error('[ItemOptions] ID inválido', type, id);
            return;
        }

        if (type === 'area') {
            if (AAAdmin && AAAdmin.AreaCreateModal && typeof AAAdmin.AreaCreateModal.openEdit === 'function') {
                AAAdmin.AreaCreateModal.openEdit(numericId);
                return;
            }
            console.error('[ItemOptions] AAAdmin.AreaCreateModal.openEdit no disponible');
            return;
        }

        if (type === 'staff') {
            if (AAAdmin && AAAdmin.StaffCreateModal && typeof AAAdmin.StaffCreateModal.openEdit === 'function') {
                AAAdmin.StaffCreateModal.openEdit(numericId);
                return;
            }
            console.error('[ItemOptions] AAAdmin.StaffCreateModal.openEdit no disponible');
            return;
        }

        if (type === 'service') {
            if (AAAdmin && AAAdmin.ServiceCreateModal && typeof AAAdmin.ServiceCreateModal.openEdit === 'function') {
                AAAdmin.ServiceCreateModal.openEdit(numericId);
                return;
            }
            console.error('[ItemOptions] AAAdmin.ServiceCreateModal.openEdit no disponible');
        }
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var trigger = target && target.closest
            ? target.closest('[data-aa-item-options-trigger]')
            : null;

        if (trigger && !trigger.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            toggleMenu(
                asString(trigger.getAttribute('data-aa-item-type')).trim(),
                asString(trigger.getAttribute('data-aa-item-id')).trim()
            );
            return;
        }

        var menuItem = target && target.closest
            ? target.closest('.aa-assignment-item-options-menu [role="menuitem"]')
            : null;

        if (menuItem) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            var action = asString(menuItem.getAttribute('data-aa-item-action')).trim();
            var type = asString(menuItem.getAttribute('data-aa-item-type')).trim();
            var id = asString(menuItem.getAttribute('data-aa-item-id')).trim();

            closeAllMenus();

            if (action === 'edit') {
                openEditModal(type, id);
            }

            return;
        }

        if (openItemKey !== '' && !isInsideItemOptions(target)) {
            closeAllMenus();
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || openItemKey === '') {
            return;
        }

        closeAllMenus();
    }

    function handleSectionToggle(event) {
        var details = event.target;

        if (!details || !details.classList || !details.classList.contains('aa-module-section-card')) {
            return;
        }

        if (!details.open && openItemKey !== '') {
            closeAllMenus();
        }
    }

    function handleViewportChange() {
        if (openItemKey !== '') {
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

    function bindSavedListeners() {
        document.addEventListener('aa:area:saved', closeAllMenus);
        document.addEventListener('aa:staff:saved', closeAllMenus);
        document.addEventListener('aa:service:saved', closeAllMenus);
    }

    function bind() {
        if (isBound) {
            return;
        }

        isBound = true;
        document.addEventListener('click', handleDocumentClick, true);
        document.addEventListener('keydown', handleDocumentKeydown);
        document.addEventListener('toggle', handleSectionToggle, true);
        bindViewportDismissHandlers();
        bindSavedListeners();
    }

    globalRoot.AAAdmin = globalRoot.AAAdmin || {};
    globalRoot.AAAdmin.renderAssignmentItemOptions = renderAssignmentItemOptions;

    if (typeof document === 'undefined') {
        if (typeof module !== 'undefined' && module.exports) {
            module.exports = {
                bind: bind,
                closeAllMenus: closeAllMenus,
                openEditModal: openEditModal,
                openMenu: openMenu,
                parseItemKey: parseItemKey,
                renderAssignmentItemOptions: renderAssignmentItemOptions,
                toggleMenu: toggleMenu
            };
        }
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
            openEditModal: openEditModal,
            openMenu: openMenu,
            parseItemKey: parseItemKey,
            renderAssignmentItemOptions: renderAssignmentItemOptions,
            toggleMenu: toggleMenu
        };
    }
})();

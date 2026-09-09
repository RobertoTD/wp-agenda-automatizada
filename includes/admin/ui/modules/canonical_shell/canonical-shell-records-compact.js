/**
 * Canonical Shell — registros compactos con overlay (Ciclo 5 / decisión 25).
 * Solo opera en fill/resolved_page cuando existe #aa-shell-records-list.
 */
(function () {
    'use strict';

    var list = document.getElementById('aa-shell-records-list');
    var extender = document.getElementById('aa-shell-records-scroll-extender');
    var scrollport = list ? list.closest('.aa-shell-list-panel-body') : null;
    if (!list || !extender || !scrollport) {
        return;
    }

    var openLi = null;
    var panelResizeObserver = null;
    var openMenu = null;
    var openMenuTrigger = null;

    var createModal = document.getElementById('aa-shell-record-modal');
    var deleteModal = document.getElementById('aa-shell-delete-record-modal');

    function yContent(el) {
        var er = el.getBoundingClientRect();
        var sr = scrollport.getBoundingClientRect();
        return (er.top - sr.top) + scrollport.scrollTop;
    }

    function isModalLayerOpen() {
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            return true;
        }
        if (createModal && !createModal.classList.contains('hidden')) {
            return true;
        }
        return false;
    }

    function isMenuVisible(menu) {
        return !!(menu
            && !menu.classList.contains('hidden')
            && !menu.hasAttribute('hidden'));
    }

    function clearInert() {
        var items = list.querySelectorAll('[data-aa-shell-record]');
        for (var i = 0; i < items.length; i++) {
            items[i].removeAttribute('inert');
        }
    }

    function applyInert(overlayBottom) {
        clearInert();
        if (!openLi) {
            return;
        }
        var items = list.querySelectorAll('[data-aa-shell-record]');
        var passedOpen = false;
        for (var i = 0; i < items.length; i++) {
            var li = items[i];
            if (li === openLi) {
                passedOpen = true;
                continue;
            }
            if (!passedOpen) {
                continue;
            }
            if (yContent(li) < overlayBottom) {
                li.setAttribute('inert', '');
            }
        }
    }

    function setExtenderHeight(px) {
        var next = Math.max(0, Math.round(px));
        var current = parseInt(extender.style.height, 10);
        if (!isFinite(current)) {
            current = 0;
        }
        if (Math.abs(next - current) < 1 && extender.style.height !== '') {
            if (next === 0 && extender.style.height === '0px') {
                return;
            }
            if (next > 0 && extender.style.height === next + 'px') {
                return;
            }
        }
        extender.style.height = next + 'px';
    }

    function remeasure() {
        if (!openLi) {
            setExtenderHeight(0);
            clearInert();
            return;
        }
        var panel = openLi.querySelector('.aa-shell-record-panel');
        var items = list.querySelectorAll('[data-aa-shell-record]');
        var lastLi = items.length ? items[items.length - 1] : null;
        if (!panel || !lastLi || panel.hasAttribute('hidden')) {
            setExtenderHeight(0);
            clearInert();
            return;
        }
        var naturalBottom = yContent(lastLi) + lastLi.offsetHeight;
        var overlayBottom = yContent(panel) + panel.offsetHeight;
        if (isMenuVisible(openMenu)) {
            var menuBottom = yContent(openMenu) + openMenu.offsetHeight;
            if (menuBottom > overlayBottom) {
                overlayBottom = menuBottom;
            }
        }
        var extra = Math.max(0, Math.round(overlayBottom - naturalBottom));
        setExtenderHeight(extra);
        applyInert(overlayBottom);
    }

    function unobserveMenu(menu) {
        if (panelResizeObserver && menu) {
            try {
                panelResizeObserver.unobserve(menu);
            } catch (e) {
                /* ignore */
            }
        }
    }

    function closeMenu(restoreFocus) {
        if (!openMenu) {
            return;
        }
        var menu = openMenu;
        openMenu.classList.add('hidden');
        openMenu.setAttribute('hidden', '');
        if (openMenuTrigger) {
            openMenuTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && typeof openMenuTrigger.focus === 'function') {
                openMenuTrigger.focus();
            }
        }
        unobserveMenu(menu);
        openMenu = null;
        openMenuTrigger = null;
        remeasure();
    }

    function openMenuFor(trigger, menu) {
        closeMenu(false);
        openMenu = menu;
        openMenuTrigger = trigger;
        menu.classList.remove('hidden');
        menu.removeAttribute('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        if (panelResizeObserver) {
            panelResizeObserver.observe(menu);
        }
        remeasure();
    }

    function detachPanelObserver() {
        if (panelResizeObserver) {
            panelResizeObserver.disconnect();
            panelResizeObserver = null;
        }
    }

    function closeRecord() {
        if (!openLi) {
            return;
        }
        closeMenu(false);
        var toggle = openLi.querySelector('.aa-shell-record-toggle');
        var panel = openLi.querySelector('.aa-shell-record-panel');
        openLi.classList.remove('is-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
        if (panel) {
            panel.setAttribute('hidden', '');
        }
        openLi = null;
        detachPanelObserver();
        setExtenderHeight(0);
        clearInert();
    }

    function openRecord(li) {
        if (openLi === li) {
            return;
        }
        if (openLi) {
            closeRecord();
        }
        var toggle = li.querySelector('.aa-shell-record-toggle');
        var panel = li.querySelector('.aa-shell-record-panel');
        if (!toggle || !panel) {
            return;
        }
        li.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        panel.removeAttribute('hidden');
        openLi = li;
        detachPanelObserver();
        if (typeof ResizeObserver !== 'undefined') {
            panelResizeObserver = new ResizeObserver(function () {
                remeasure();
            });
            panelResizeObserver.observe(panel);
        }
        remeasure();
    }

    function toggleRecord(li) {
        if (openLi === li) {
            closeRecord();
            return;
        }
        openRecord(li);
    }

    function findOptionsMenu(optionsTrigger) {
        var header = optionsTrigger.closest('.aa-shell-record-header');
        return header ? header.querySelector('.aa-shell-record-options-menu') : null;
    }

    list.addEventListener('click', function (event) {
        var optionsTrigger = event.target.closest('.aa-shell-record-options-trigger');
        if (optionsTrigger && list.contains(optionsTrigger)) {
            event.preventDefault();
            event.stopPropagation();
            var menu = findOptionsMenu(optionsTrigger);
            if (!menu) {
                return;
            }
            if (openMenu === menu) {
                closeMenu(true);
                return;
            }
            openMenuFor(optionsTrigger, menu);
            return;
        }

        if (event.target.closest('.aa-shell-edit-record-btn, .aa-shell-delete-record-btn')) {
            closeMenu(false);
            return;
        }

        if (event.target.closest('.aa-shell-record-options-menu')) {
            event.stopPropagation();
            return;
        }

        var toggle = event.target.closest('.aa-shell-record-toggle');
        if (toggle && list.contains(toggle)) {
            var li = toggle.closest('[data-aa-shell-record]');
            if (li) {
                toggleRecord(li);
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (!openMenu) {
            return;
        }
        if (event.target.closest('.aa-shell-record-options, .aa-shell-record-options-menu')) {
            return;
        }
        closeMenu(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        if (isModalLayerOpen()) {
            return;
        }
        if (openMenu) {
            closeMenu(true);
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            return;
        }
        if (openLi) {
            closeRecord();
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
        }
    });

    window.addEventListener('resize', function () {
        remeasure();
    });

    document.addEventListener('aa-shell-list-details-toggle', function () {
        remeasure();
    });

    window.AA_ShellRecordsCompact = {
        remeasure: remeasure,
        closeOpen: closeRecord
    };
})();

/**
 * Executable Options Menu Placement — posicionamiento fixed compartido para menús ⋮.
 */
(function () {
    'use strict';

    var DEFAULT_MARGIN = 8;
    var DEFAULT_GAP = 8;
    var FIXED_Z_INDEX = 70;

    /**
     * @param {{
     *   triggerRect: {top:number,left:number,right:number,bottom:number,width:number,height:number},
     *   menuRect: {width:number,height:number},
     *   viewportWidth: number,
     *   viewportHeight: number,
     *   margin?: number,
     *   gap?: number
     * }} input
     * @returns {{top:number,left:number,mode:'down'|'up'|'clamped'}}
     */
    function resolveOptionsMenuPlacement(input) {
        var margin = typeof input.margin === 'number' ? input.margin : DEFAULT_MARGIN;
        var gap = typeof input.gap === 'number' ? input.gap : DEFAULT_GAP;
        var triggerRect = input.triggerRect;
        var menuRect = input.menuRect;
        var viewportWidth = input.viewportWidth;
        var viewportHeight = input.viewportHeight;
        var menuWidth = menuRect.width;
        var menuHeight = menuRect.height;
        var downTop = triggerRect.bottom + gap;
        var upTop = triggerRect.top - menuHeight - gap;
        var fitsDown = downTop + menuHeight <= viewportHeight - margin;
        var fitsUp = upTop >= margin;
        var top;
        var mode;

        if (fitsDown) {
            top = downTop;
            mode = 'down';
        } else if (fitsUp) {
            top = upTop;
            mode = 'up';
        } else {
            top = Math.max(margin, Math.min(downTop, viewportHeight - menuHeight - margin));
            mode = 'clamped';
        }

        var left = triggerRect.right - menuWidth;
        var maxLeft = viewportWidth - menuWidth - margin;

        left = Math.max(margin, Math.min(left, maxLeft));

        return {
            top: top,
            left: left,
            mode: mode
        };
    }

    /**
     * @param {HTMLElement|null} menu
     */
    function resetOptionsMenuPlacement(menu) {
        if (!menu) {
            return;
        }

        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
        menu.style.zIndex = '';
        menu.classList.remove('bottom-full', 'mb-2');
        menu.classList.add('top-full', 'mt-2');
    }

    /**
     * @param {HTMLElement|null} menu
     * @param {HTMLElement|null} trigger
     * @param {{margin?:number,gap?:number,zIndex?:number}} [options]
     */
    function positionOptionsMenu(menu, trigger, options) {
        if (!menu || !trigger) {
            return;
        }

        var opts = options || {};
        var margin = typeof opts.margin === 'number' ? opts.margin : DEFAULT_MARGIN;
        var gap = typeof opts.gap === 'number' ? opts.gap : DEFAULT_GAP;
        var zIndex = typeof opts.zIndex === 'number' ? opts.zIndex : FIXED_Z_INDEX;

        menu.classList.remove('top-full', 'bottom-full', 'mt-2', 'mb-2');
        menu.style.position = 'fixed';
        menu.style.zIndex = String(zIndex);
        menu.style.right = '';
        menu.style.bottom = '';

        var triggerRect = trigger.getBoundingClientRect();
        var menuRect = menu.getBoundingClientRect();
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        var placement = resolveOptionsMenuPlacement({
            triggerRect: triggerRect,
            menuRect: menuRect,
            viewportWidth: viewportWidth,
            viewportHeight: viewportHeight,
            margin: margin,
            gap: gap
        });

        menu.style.top = placement.top + 'px';
        menu.style.left = placement.left + 'px';
    }

    var api = {
        DEFAULT_MARGIN: DEFAULT_MARGIN,
        DEFAULT_GAP: DEFAULT_GAP,
        FIXED_Z_INDEX: FIXED_Z_INDEX,
        resolveOptionsMenuPlacement: resolveOptionsMenuPlacement,
        resetOptionsMenuPlacement: resetOptionsMenuPlacement,
        positionOptionsMenu: positionOptionsMenu
    };

    if (typeof window !== 'undefined') {
        window.AAExecutableOptionsMenuPlacement = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();

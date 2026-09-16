/**
 * Canonical shell — galería de imágenes en card/panel.
 *
 * No depende de módulos de producto Expedientes. Infra de firma/delete:
 * contratos canónicos existentes (aa_sign_canonical_record_image_read,
 * aa_delete_canonical_record_image).
 *
 * Utilidades PHP compartidas (nombres históricos; conservar al deprecar
 * Expedientes UI): ver cabecera de class-aa-canonical-images-shell-presenter.php.
 */
(function (global) {
    'use strict';

    var READ_SUMMARY = 'summary';
    var READ_GALLERY = 'gallery';
    var READ_DISPLAY = 'display';

    var controllersByRecord = Object.create(null);
    var urlCache = Object.create(null);
    var viewerOpen = false;
    var viewerEpoch = 0;
    var viewerAbort = null;
    var viewerPreviousFocus = null;
    var inited = false;

    function cfg() {
        return (global.AA_CANONICAL_SHELL_RECORD_FORM && typeof global.AA_CANONICAL_SHELL_RECORD_FORM === 'object')
            ? global.AA_CANONICAL_SHELL_RECORD_FORM
            : null;
    }

    function cacheKey(imageId, version) {
        return String(imageId) + ':' + String(version);
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function setImgInto(box, url, className) {
        if (!box) {
            return;
        }
        clearNode(box);
        var img = document.createElement('img');
        img.className = className || 'aa-shell-record-gallery-img';
        img.src = url;
        img.alt = '';
        img.decoding = 'async';
        img.loading = 'lazy';
        box.appendChild(img);
    }

    function signRead(recordId, imageId, version, signal) {
        var c = cfg();
        if (!c || !c.ajaxUrl || !c.signReadAction || !c.signReadNonce) {
            return Promise.reject(new Error('sign_read_unavailable'));
        }
        var key = cacheKey(imageId, version);
        var cached = urlCache[key];
        if (cached && typeof cached.url === 'string' && cached.url !== ''
            && typeof cached.expiresAt === 'number' && Date.now() < cached.expiresAt - 5000) {
            return Promise.resolve(cached.url);
        }

        var body = new FormData();
        body.append('action', c.signReadAction);
        body.append('nonce', c.signReadNonce);
        body.append('family_key', String(c.familyKey || ''));
        body.append('container_id', String(c.containerId || ''));
        body.append('record_id', String(recordId));
        body.append('image_id', String(imageId));
        body.append('variant', String(version));

        return fetch(c.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
            signal: signal || undefined
        }).then(function (response) {
            return response.text().then(function (text) {
                return { okHttp: response.ok, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || payload.success !== true || !payload.data) {
                var err = new Error('sign_read_failed');
                err.code = payload && payload.data && payload.data.code
                    ? String(payload.data.code)
                    : 'sign_read_failed';
                throw err;
            }
            var url = typeof payload.data.url === 'string' ? payload.data.url : '';
            var expiresIn = typeof payload.data.expires_in === 'number' ? payload.data.expires_in : 0;
            var gotVersion = typeof payload.data.variant === 'string' ? payload.data.variant : '';
            if (url === '' || !/^https?:\/\//i.test(url) || gotVersion !== version || expiresIn < 1) {
                throw new Error('sign_read_invalid');
            }
            urlCache[key] = {
                url: url,
                expiresAt: Date.now() + (expiresIn * 1000)
            };
            return url;
        });
    }

    function GalleryController(root) {
        this.root = root;
        this.recordId = parseInt(root.getAttribute('data-aa-gallery-record-id') || '0', 10);
        this.mainBtn = root.querySelector('.aa-shell-record-gallery-main');
        this.deleteBtn = root.querySelector('.aa-shell-record-gallery-delete');
        this.strip = root.querySelector('.aa-shell-record-gallery-strip');
        this.counter = root.querySelector('[data-aa-gallery-counter]');
        this.errorEl = root.querySelector('[data-aa-gallery-error]');
        this.imageIds = [];
        this.selectedId = 0;
        this.mainGen = 0;
        this.miniAborts = Object.create(null);
        this.observer = null;
        this.bound = false;

        var minis = root.querySelectorAll('.aa-shell-record-gallery-mini');
        for (var i = 0; i < minis.length; i++) {
            var mid = parseInt(minis[i].getAttribute('data-aa-image-id') || '0', 10);
            if (mid >= 1) {
                this.imageIds.push(mid);
            }
        }
        if (this.imageIds.length === 0 && this.mainBtn) {
            var only = parseInt(this.mainBtn.getAttribute('data-aa-image-id') || '0', 10);
            if (only >= 1) {
                this.imageIds.push(only);
            }
        }
        this.selectedId = parseInt(root.getAttribute('data-aa-selected-id') || '0', 10);
        if (!(this.selectedId >= 1) || this.imageIds.indexOf(this.selectedId) === -1) {
            this.selectedId = this.imageIds.length > 0 ? this.imageIds[0] : 0;
        }
    }

    GalleryController.prototype.setError = function (message) {
        if (!this.errorEl) {
            return;
        }
        if (!message) {
            this.errorEl.textContent = '';
            this.errorEl.classList.add('hidden');
            this.errorEl.setAttribute('hidden', '');
            return;
        }
        this.errorEl.textContent = message;
        this.errorEl.classList.remove('hidden');
        this.errorEl.removeAttribute('hidden');
    };

    GalleryController.prototype.updateDeletePayload = function () {
        if (!this.deleteBtn || !(this.selectedId >= 1) || !(this.recordId >= 1)) {
            return;
        }
        this.deleteBtn.setAttribute('data-aa-image', JSON.stringify({
            id: this.selectedId,
            record_id: this.recordId
        }));
    };

    GalleryController.prototype.updateCounter = function () {
        if (!this.counter) {
            return;
        }
        var total = this.imageIds.length;
        if (total <= 1) {
            this.counter.textContent = '';
            return;
        }
        var idx = this.imageIds.indexOf(this.selectedId);
        var pos = idx >= 0 ? (idx + 1) : 1;
        this.counter.textContent = String(pos) + ' de ' + String(total);
    };

    GalleryController.prototype.syncMiniSelection = function () {
        if (!this.strip) {
            return;
        }
        var minis = this.strip.querySelectorAll('.aa-shell-record-gallery-mini');
        for (var i = 0; i < minis.length; i++) {
            var mid = parseInt(minis[i].getAttribute('data-aa-image-id') || '0', 10);
            var selected = mid === this.selectedId;
            if (selected) {
                minis[i].classList.add('aa-shell-record-gallery-mini-selected');
            } else {
                minis[i].classList.remove('aa-shell-record-gallery-mini-selected');
            }
            minis[i].setAttribute('aria-pressed', selected ? 'true' : 'false');
        }
    };

    GalleryController.prototype.loadMain = function () {
        var self = this;
        if (!this.mainBtn || !(this.selectedId >= 1) || !(this.recordId >= 1)) {
            return;
        }
        this.mainGen += 1;
        var gen = this.mainGen;
        var imageId = this.selectedId;
        this.mainBtn.setAttribute('data-aa-image-id', String(imageId));
        this.updateDeletePayload();
        this.updateCounter();
        this.syncMiniSelection();
        this.root.setAttribute('data-aa-selected-id', String(imageId));

        var status = this.mainBtn.querySelector('.aa-shell-record-gallery-main-status');
        if (status) {
            status.textContent = 'Cargando imagen';
        }

        var ac = typeof AbortController === 'function' ? new AbortController() : null;
        signRead(this.recordId, imageId, READ_DISPLAY, ac ? ac.signal : null).then(function (url) {
            if (gen !== self.mainGen || self.selectedId !== imageId) {
                return;
            }
            setImgInto(self.mainBtn, url, 'aa-shell-record-gallery-img');
        }).catch(function (err) {
            if (gen !== self.mainGen || self.selectedId !== imageId) {
                return;
            }
            if (err && err.name === 'AbortError') {
                return;
            }
            self.setError('No se pudo cargar la imagen.');
        });
    };

    GalleryController.prototype.loadMini = function (miniBtn) {
        var self = this;
        if (!miniBtn || !(this.recordId >= 1)) {
            return;
        }
        var imageId = parseInt(miniBtn.getAttribute('data-aa-image-id') || '0', 10);
        if (!(imageId >= 1)) {
            return;
        }
        if (miniBtn.querySelector('img')) {
            return;
        }
        if (this.miniAborts[imageId]) {
            try {
                this.miniAborts[imageId].abort();
            } catch (e) { /* ignore */ }
        }
        var ac = typeof AbortController === 'function' ? new AbortController() : null;
        this.miniAborts[imageId] = ac;
        var expectedSelected = this.selectedId;
        signRead(this.recordId, imageId, READ_GALLERY, ac ? ac.signal : null).then(function (url) {
            if (self.miniAborts[imageId] !== ac) {
                return;
            }
            // Mini no depende de selección; solo evita pintar si el nodo ya no existe.
            if (!miniBtn.isConnected) {
                return;
            }
            setImgInto(miniBtn, url, 'aa-shell-record-gallery-img');
            void expectedSelected;
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                return;
            }
        });
    };

    GalleryController.prototype.select = function (imageId) {
        imageId = parseInt(imageId, 10);
        if (!(imageId >= 1) || this.imageIds.indexOf(imageId) === -1) {
            return;
        }
        if (imageId === this.selectedId) {
            return;
        }
        this.setError('');
        this.selectedId = imageId;
        this.loadMain();
    };

    GalleryController.prototype.isPanelVisible = function () {
        var panel = this.root.closest
            ? this.root.closest('.aa-shell-record-panel')
            : null;
        if (panel && panel.hasAttribute('hidden')) {
            return false;
        }
        return true;
    };

    GalleryController.prototype.ensureLoaded = function () {
        if (this._mainLoaded) {
            return;
        }
        if (!this.isPanelVisible()) {
            return;
        }
        this._mainLoaded = true;
        this.loadMain();
        if (typeof IntersectionObserver === 'function' && this.strip) {
            this.observer = new IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (!entries[i].isIntersecting) {
                        continue;
                    }
                    this.loadMini(entries[i].target);
                }
            }.bind(this), { root: null, rootMargin: '40px', threshold: 0.01 });
            var minis = this.strip.querySelectorAll('.aa-shell-record-gallery-mini');
            for (var m = 0; m < minis.length; m++) {
                this.observer.observe(minis[m]);
            }
        } else if (this.strip) {
            var all = this.strip.querySelectorAll('.aa-shell-record-gallery-mini');
            for (var j = 0; j < all.length; j++) {
                this.loadMini(all[j]);
            }
        }
    };

    GalleryController.prototype.watchPanel = function () {
        var self = this;
        var panel = this.root.closest
            ? this.root.closest('.aa-shell-record-panel')
            : null;
        if (!panel || typeof MutationObserver !== 'function') {
            return;
        }
        this.panelObserver = new MutationObserver(function () {
            if (self.isPanelVisible()) {
                self.ensureLoaded();
            }
        });
        this.panelObserver.observe(panel, { attributes: true, attributeFilter: ['hidden'] });
    };

    GalleryController.prototype.bind = function () {
        var self = this;
        if (this.bound) {
            return;
        }
        this.bound = true;
        this._mainLoaded = false;

        if (this.mainBtn) {
            this.mainBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openViewer(self.recordId, self.selectedId, self.mainBtn);
            });
        }

        if (this.strip) {
            this.strip.addEventListener('click', function (e) {
                var mini = e.target && e.target.closest
                    ? e.target.closest('.aa-shell-record-gallery-mini')
                    : null;
                if (!mini || !self.strip.contains(mini)) {
                    return;
                }
                e.preventDefault();
                var mid = parseInt(mini.getAttribute('data-aa-image-id') || '0', 10);
                self.select(mid);
            });
        }

        this.watchPanel();
        this.ensureLoaded();
    };

    GalleryController.prototype.destroy = function () {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
        if (this.panelObserver) {
            this.panelObserver.disconnect();
            this.panelObserver = null;
        }
        Object.keys(this.miniAborts).forEach(function (k) {
            try {
                if (this.miniAborts[k]) {
                    this.miniAborts[k].abort();
                }
            } catch (e) { /* ignore */ }
        }, this);
        this.miniAborts = Object.create(null);
        this.mainGen += 1;
    };

    GalleryController.prototype.afterRetire = function (imageId) {
        imageId = parseInt(imageId, 10);
        var before = this.imageIds.slice();
        var idx = before.indexOf(imageId);
        if (idx === -1) {
            return;
        }
        var remaining = before.filter(function (id) {
            return id !== imageId;
        });
        this.imageIds = remaining;

        if (remaining.length === 0) {
            this.destroy();
            if (this.root && this.root.parentNode) {
                this.root.parentNode.removeChild(this.root);
            }
            removeSummaryForRecord(this.recordId);
            delete controllersByRecord[String(this.recordId)];
            return;
        }

        var nextId = remaining[idx] !== undefined
            ? remaining[idx]
            : (remaining[idx - 1] !== undefined ? remaining[idx - 1] : remaining[0]);

        if (this.strip) {
            var mini = this.strip.querySelector('.aa-shell-record-gallery-mini[data-aa-image-id="' + imageId + '"]');
            if (mini && mini.parentNode) {
                if (this.observer) {
                    try {
                        this.observer.unobserve(mini);
                    } catch (e) { /* ignore */ }
                }
                mini.parentNode.removeChild(mini);
            }
            if (remaining.length === 1) {
                if (this.strip.parentNode) {
                    this.strip.parentNode.removeChild(this.strip);
                }
                this.strip = null;
                if (this.counter && this.counter.parentNode) {
                    this.counter.parentNode.removeChild(this.counter);
                }
                this.counter = null;
            } else {
                var minis = this.strip.querySelectorAll('.aa-shell-record-gallery-mini');
                for (var i = 0; i < minis.length; i++) {
                    minis[i].setAttribute(
                        'aria-label',
                        'Ver imagen ' + String(i + 1) + ' de ' + String(remaining.length)
                    );
                }
            }
        }

        this.selectedId = nextId;
        this._mainLoaded = true;
        this.loadMain();
        updateSummaryAfterRetire(this.recordId, imageId, remaining[0]);
    };

    function removeSummaryForRecord(recordId) {
        var nodes = document.querySelectorAll(
            '[data-aa-summary-for-record="' + String(recordId) + '"]'
        );
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].parentNode) {
                nodes[i].parentNode.removeChild(nodes[i]);
            }
        }
    }

    function updateSummaryAfterRetire(recordId, deletedId, newLatestId) {
        var nodes = document.querySelectorAll(
            '[data-aa-summary-for-record="' + String(recordId) + '"]'
        );
        if (!nodes.length) {
            return;
        }
        var currentId = parseInt(nodes[0].getAttribute('data-aa-image-id') || '0', 10);
        if (currentId !== deletedId) {
            return;
        }
        // La cabecera mostraba la eliminada (era la última): re-firmar summary de la nueva última.
        var ac = typeof AbortController === 'function' ? new AbortController() : null;
        signRead(recordId, newLatestId, READ_SUMMARY, ac ? ac.signal : null).then(function (url) {
            for (var i = 0; i < nodes.length; i++) {
                if (!nodes[i].isConnected) {
                    continue;
                }
                nodes[i].setAttribute('data-aa-image-id', String(newLatestId));
                var img = nodes[i].querySelector('img');
                if (img) {
                    img.src = url;
                }
            }
        }).catch(function () {
            removeSummaryForRecord(recordId);
        });
    }

    function getViewerEls() {
        return {
            modal: document.getElementById('aa-shell-image-viewer-modal'),
            backdrop: document.getElementById('aa-shell-image-viewer-modal-backdrop'),
            closeBtn: document.getElementById('aa-shell-image-viewer-modal-close-btn'),
            body: document.getElementById('aa-shell-image-viewer-body')
        };
    }

    function closeViewer() {
        var els = getViewerEls();
        viewerEpoch += 1;
        viewerOpen = false;
        if (viewerAbort) {
            try {
                viewerAbort.abort();
            } catch (e) { /* ignore */ }
            viewerAbort = null;
        }
        if (els.modal) {
            els.modal.classList.add('hidden');
            els.modal.setAttribute('aria-hidden', 'true');
        }
        if (els.body) {
            clearNode(els.body);
            var status = document.createElement('p');
            status.id = 'aa-shell-image-viewer-status';
            status.className = 'text-sm text-gray-500 m-0';
            status.setAttribute('role', 'status');
            status.textContent = 'Cargando imagen…';
            els.body.appendChild(status);
        }
        if (viewerPreviousFocus && typeof viewerPreviousFocus.focus === 'function') {
            try {
                viewerPreviousFocus.focus();
            } catch (e2) { /* ignore */ }
        }
        viewerPreviousFocus = null;
    }

    function openViewer(recordId, imageId, focusEl) {
        var els = getViewerEls();
        if (!els.modal || !els.body || !(recordId >= 1) || !(imageId >= 1)) {
            return;
        }
        viewerEpoch += 1;
        var epoch = viewerEpoch;
        viewerOpen = true;
        viewerPreviousFocus = focusEl || document.activeElement;
        if (viewerAbort) {
            try {
                viewerAbort.abort();
            } catch (e) { /* ignore */ }
        }
        viewerAbort = typeof AbortController === 'function' ? new AbortController() : null;

        clearNode(els.body);
        var status = document.createElement('p');
        status.className = 'text-sm text-gray-500 m-0';
        status.setAttribute('role', 'status');
        status.textContent = 'Cargando imagen…';
        els.body.appendChild(status);

        els.modal.classList.remove('hidden');
        els.modal.setAttribute('aria-hidden', 'false');
        if (els.closeBtn && typeof els.closeBtn.focus === 'function') {
            els.closeBtn.focus();
        }

        signRead(recordId, imageId, READ_DISPLAY, viewerAbort ? viewerAbort.signal : null).then(function (url) {
            if (!viewerOpen || epoch !== viewerEpoch) {
                return;
            }
            clearNode(els.body);
            var img = document.createElement('img');
            img.className = 'aa-shell-image-viewer-img';
            img.src = url;
            img.alt = '';
            img.decoding = 'async';
            els.body.appendChild(img);
        }).catch(function (err) {
            if (!viewerOpen || epoch !== viewerEpoch) {
                return;
            }
            if (err && err.name === 'AbortError') {
                return;
            }
            clearNode(els.body);
            var errEl = document.createElement('p');
            errEl.className = 'text-sm text-red-600 m-0';
            errEl.setAttribute('role', 'status');
            errEl.textContent = 'No se pudo cargar la imagen.';
            els.body.appendChild(errEl);
        });
    }

    function bindViewerOnce() {
        var els = getViewerEls();
        if (!els.modal || els.modal.getAttribute('data-aa-viewer-bound') === '1') {
            return;
        }
        els.modal.setAttribute('data-aa-viewer-bound', '1');
        if (els.closeBtn) {
            els.closeBtn.addEventListener('click', function () {
                closeViewer();
            });
        }
        if (els.backdrop) {
            els.backdrop.addEventListener('click', function () {
                closeViewer();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !viewerOpen) {
                return;
            }
            closeViewer();
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
        });
    }

    function mountGallery(root) {
        var recordId = parseInt(root.getAttribute('data-aa-gallery-record-id') || '0', 10);
        if (!(recordId >= 1)) {
            return;
        }
        var existing = controllersByRecord[String(recordId)];
        if (existing) {
            existing.destroy();
        }
        var ctrl = new GalleryController(root);
        controllersByRecord[String(recordId)] = ctrl;
        ctrl.bind();
    }

    function init() {
        if (inited) {
            return;
        }
        var c = cfg();
        if (!c || !c.signReadAction || !c.signReadNonce) {
            return;
        }
        inited = true;
        bindViewerOnce();
        var roots = document.querySelectorAll('[data-aa-gallery]');
        for (var i = 0; i < roots.length; i++) {
            mountGallery(roots[i]);
        }
    }

    function afterRetire(imageId) {
        imageId = parseInt(imageId, 10);
        if (!(imageId >= 1)) {
            return;
        }
        var keys = Object.keys(controllersByRecord);
        for (var i = 0; i < keys.length; i++) {
            var ctrl = controllersByRecord[keys[i]];
            if (ctrl && ctrl.imageIds.indexOf(imageId) !== -1) {
                ctrl.afterRetire(imageId);
                return;
            }
        }
        // Resumen/banner fuera de galería.
        var orphans = document.querySelectorAll('[data-aa-image-id="' + String(imageId) + '"]');
        for (var j = 0; j < orphans.length; j++) {
            var node = orphans[j];
            if (node.closest && node.closest('[data-aa-gallery]')) {
                continue;
            }
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        }
    }

    global.AACanonicalShellImagesGallery = {
        init: init,
        afterRetire: afterRetire,
        openViewer: openViewer,
        closeViewer: closeViewer,
        READ_SUMMARY: READ_SUMMARY,
        READ_GALLERY: READ_GALLERY,
        READ_DISPLAY: READ_DISPLAY
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : this);

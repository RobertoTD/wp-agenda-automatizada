/**
 * Tutorial session suppression — ephemeral hide for the current authenticated WP session.
 *
 * Uses sessionStorage scoped by blogId + flowId + authSessionId from AA_ADMIN_CONTEXT.
 * Does not modify durable tutorial state.
 */
(function () {
    'use strict';

    var KEY_PREFIX = 'aa_tutorial_suppressed_v1';
    var FALLBACK_AUTH_SESSION_ID = '__tab_session__';
    var SUPPRESSION_VALUE = '1';

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function resolveBlogId() {
        if (window.AATutorialSession && typeof window.AATutorialSession.resolveBlogId === 'function') {
            return window.AATutorialSession.resolveBlogId();
        }

        var ctx = window.AA_ADMIN_CONTEXT;
        return ctx && ctx.blogId != null ? String(ctx.blogId) : '';
    }

    function resolveAuthSessionId() {
        var ctx = window.AA_ADMIN_CONTEXT || {};
        var id = normalizeString(ctx.authSessionId);

        if (id) {
            return id;
        }

        return FALLBACK_AUTH_SESSION_ID;
    }

    function getStorage() {
        if (typeof window === 'undefined' || !window.sessionStorage) {
            return null;
        }

        return window.sessionStorage;
    }

    /**
     * @param {string} blogId
     * @param {string} flowId
     * @param {string} [authSessionId]
     * @returns {string}
     */
    function buildKey(blogId, flowId, authSessionId) {
        return KEY_PREFIX
            + ':'
            + normalizeString(blogId)
            + ':'
            + normalizeString(flowId)
            + ':'
            + normalizeString(authSessionId || resolveAuthSessionId());
    }

    /**
     * @param {string} blogId
     * @param {string} flowId
     * @returns {boolean}
     */
    function isSuppressed(blogId, flowId) {
        var storage = getStorage();
        var bid = normalizeString(blogId) || resolveBlogId();
        var fid = normalizeString(flowId);

        if (!storage || !bid || !fid) {
            return false;
        }

        return storage.getItem(buildKey(bid, fid, resolveAuthSessionId())) === SUPPRESSION_VALUE;
    }

    /**
     * @param {string} blogId
     * @param {string} flowId
     * @returns {boolean}
     */
    function suppress(blogId, flowId) {
        var storage = getStorage();
        var bid = normalizeString(blogId) || resolveBlogId();
        var fid = normalizeString(flowId);

        if (!storage || !bid || !fid) {
            return false;
        }

        storage.setItem(buildKey(bid, fid, resolveAuthSessionId()), SUPPRESSION_VALUE);
        return true;
    }

    window.TutorialSessionSuppression = {
        KEY_PREFIX: KEY_PREFIX,
        FALLBACK_AUTH_SESSION_ID: FALLBACK_AUTH_SESSION_ID,
        resolveAuthSessionId: resolveAuthSessionId,
        buildKey: buildKey,
        isSuppressed: isSuppressed,
        suppress: suppress
    };
})();

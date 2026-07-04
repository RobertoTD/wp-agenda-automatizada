/**
 * Ephemeral in-memory tutorial context for Fast Appointment (B1).
 *
 * Backend durable state remains authoritative via TutorialStateService.
 */
(function () {
    'use strict';

    var activePayload = null;

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function isValidPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        return normalizeString(payload.tutorialId) !== ''
            && normalizeString(payload.stepId) !== ''
            && normalizeString(payload.source) !== '';
    }

    function payloadsEqual(left, right) {
        return left.tutorialId === right.tutorialId
            && left.stepId === right.stepId
            && left.source === right.source;
    }

    function freezePayload(payload) {
        return Object.freeze({
            tutorialId: payload.tutorialId,
            stepId: payload.stepId,
            source: payload.source
        });
    }

    function activate(payload) {
        if (!isValidPayload(payload)) {
            return false;
        }

        var normalized = {
            tutorialId: normalizeString(payload.tutorialId),
            stepId: normalizeString(payload.stepId),
            source: normalizeString(payload.source)
        };

        if (activePayload && payloadsEqual(activePayload, normalized)) {
            return true;
        }

        activePayload = normalized;
        return true;
    }

    function get() {
        return activePayload ? freezePayload(activePayload) : null;
    }

    function isActive() {
        return activePayload !== null;
    }

    function clear() {
        activePayload = null;
    }

    window.TutorialFastAppointmentContext = {
        activate: activate,
        get: get,
        isActive: isActive,
        clear: clear
    };
})();

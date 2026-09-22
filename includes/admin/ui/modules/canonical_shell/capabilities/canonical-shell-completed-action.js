(function () {
    'use strict';
    var modules = window.AA_CANONICAL_SHELL_CAPABILITY_ACTION_MODULES;
    var config = modules && typeof modules === 'object' ? modules.completed : null;
    if (!config) { return; }
    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-aa-capability-action="completed"]') : null;
        if (!button || button.disabled) { return; }
        event.preventDefault();
        var rawPayload = button.getAttribute('data-aa-capability-action-payload') || '';
        var payload;
        try { payload = JSON.parse(rawPayload); } catch (error) { return; }
        var recordId = parseInt(payload.record_id, 10);
        if (!recordId || !config.containerId || config.familyKey !== 'action') { return; }
        button.disabled = true;
        var data = new FormData();
        data.append('action', config.action);
        data.append('nonce', config.nonce);
        data.append('family_key', config.familyKey);
        data.append('container_id', String(config.containerId));
        data.append('record_id', String(recordId));
        var context = config.returnContext || {};
        Object.keys(context).forEach(function (key) {
            if (key === 'capability_views') {
                Object.keys(context[key] || {}).forEach(function (owner) {
                    data.append('capability_views[' + owner + ']', context[key][owner]);
                });
            } else if (context[key] !== null && context[key] !== undefined) {
                data.append(key, String(context[key]));
            }
        });
        data.append('completed', String(payload.completed === 1 || payload.completed === '1' ? 1 : 0));
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (payload && payload.success && payload.data && payload.data.redirect_url) {
                    window.location.assign(payload.data.redirect_url);
                    return;
                }
                throw new Error('completion_failed');
            })
            .catch(function () { button.disabled = false; });
    });
}());

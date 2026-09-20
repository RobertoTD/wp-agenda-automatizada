(function () {
    'use strict';
    var config = window.AA_CANONICAL_SHELL_COMPLETED;
    if (!config) { return; }
    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-aa-completion-record]') : null;
        if (!button || button.disabled) { return; }
        event.preventDefault();
        var recordId = parseInt(button.getAttribute('data-aa-completion-record'), 10);
        if (!recordId || !config.containerId || config.familyKey !== 'action') { return; }
        button.disabled = true;
        var data = new FormData();
        data.append('action', config.action);
        data.append('nonce', config.nonce);
        data.append('family_key', config.familyKey);
        data.append('container_id', String(config.containerId));
        data.append('record_id', String(recordId));
        data.append('completed', button.getAttribute('data-aa-completed') === '1' ? '0' : '1');
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

/**
 * List Edit Module — modal de edición para listas user editables (feed unified).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var isActionPending = false;

    function getService() {
        return globalRoot.TasksService || null;
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

    function openModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');

            if (globalRoot.AATasksModalUi && typeof globalRoot.AATasksModalUi.onLearningModalOpened === 'function') {
                globalRoot.AATasksModalUi.onLearningModalOpened();
            }
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }

        if (globalRoot.AATasksModalUi && typeof globalRoot.AATasksModalUi.onLearningModalClosed === 'function') {
            globalRoot.AATasksModalUi.onLearningModalClosed();
        }

        if (modalId === 'aa-task-list-edit-modal') {
            resetEditForm();
        }
    }

    function showFormError(message) {
        var errorEl = document.getElementById('aa-task-list-edit-form-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo guardar.';
        }
    }

    function clearFormError() {
        setVisible(document.getElementById('aa-task-list-edit-form-error'), false);
    }

    function resetEditForm() {
        var form = document.getElementById('aa-task-list-edit-form');

        if (form) {
            form.reset();
        }

        var listIdInput = document.getElementById('aa-task-list-edit-form-list-id');
        var importanceInput = document.getElementById('aa-task-list-edit-form-importance');

        if (listIdInput) {
            listIdInput.value = '';
        }

        if (importanceInput) {
            importanceInput.value = '0';
        }

        clearFormError();
    }

    function reloadAfterMutation() {
        var boardApi = globalRoot.AATasksBoard;
        var feedApi = globalRoot.AAExecutableUserListsVisibleFeed;
        var boardReload = boardApi && typeof boardApi.reload === 'function'
            ? boardApi.reload({ silent: true })
            : Promise.resolve();
        var feedReload = feedApi
            && typeof feedApi.isEnabled === 'function'
            && feedApi.isEnabled()
            && typeof feedApi.reload === 'function'
            ? feedApi.reload().catch(function () {})
            : Promise.resolve();

        return boardReload.then(function () {
            return feedReload;
        }).then(function () {
            var proposalApi = globalRoot.AAExecutiveProposal;

            if (proposalApi && typeof proposalApi.reload === 'function') {
                return proposalApi.reload({ silent: true }).catch(function () {});
            }

            return undefined;
        });
    }

    /**
     * @param {HTMLElement} button
     */
    function openEditModalFromButton(button) {
        var listId = button.getAttribute('data-list-id') || '';
        var title = button.getAttribute('data-list-title') || '';
        var description = button.getAttribute('data-list-description') || '';
        var importance = button.getAttribute('data-list-importance') || '0';

        if (!listId) {
            return;
        }

        var listIdInput = document.getElementById('aa-task-list-edit-form-list-id');
        var titleInput = document.getElementById('aa-task-list-edit-form-title');
        var descriptionInput = document.getElementById('aa-task-list-edit-form-description');
        var importanceInput = document.getElementById('aa-task-list-edit-form-importance');

        if (listIdInput) {
            listIdInput.value = listId;
        }

        if (titleInput) {
            titleInput.value = title;
        }

        if (descriptionInput) {
            descriptionInput.value = description;
        }

        if (importanceInput) {
            importanceInput.value = importance;
        }

        clearFormError();
        openModal('aa-task-list-edit-modal');
    }

    function submitEditForm(event) {
        event.preventDefault();

        if (isActionPending) {
            return;
        }

        var service = getService();
        var listIdInput = document.getElementById('aa-task-list-edit-form-list-id');
        var titleInput = document.getElementById('aa-task-list-edit-form-title');
        var descriptionInput = document.getElementById('aa-task-list-edit-form-description');
        var importanceInput = document.getElementById('aa-task-list-edit-form-importance');
        var listId = listIdInput ? String(listIdInput.value || '').trim() : '';
        var title = titleInput ? String(titleInput.value || '').trim() : '';

        if (!listId) {
            showFormError('No se pudo identificar la lista.');
            return;
        }

        if (!title) {
            showFormError('El título de la lista es obligatorio.');
            return;
        }

        if (!service || typeof service.updateTaskList !== 'function') {
            showFormError('Servicio no disponible.');
            return;
        }

        isActionPending = true;
        clearFormError();

        service.updateTaskList({
            list_id: listId,
            title: title,
            description: descriptionInput ? descriptionInput.value : '',
            importance: importanceInput && importanceInput.value !== '' ? importanceInput.value : 0
        })
            .then(function () {
                closeModal('aa-task-list-edit-modal');
                return reloadAfterMutation();
            })
            .catch(function (err) {
                showFormError((err && err.message) ? err.message : 'No se pudo actualizar la lista.');
            })
            .finally(function () {
                isActionPending = false;
            });
    }

    function bindEditDelegation() {
        var root = document.getElementById('aa-tasks-module-root');

        if (!root || root.dataset.listEditBound === '1') {
            return;
        }

        root.dataset.listEditBound = '1';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-aa-list-edit]');

            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openEditModalFromButton(button);
        }, true);
    }

    function bindEditForm() {
        var form = document.getElementById('aa-task-list-edit-form');

        if (!form || form.dataset.bound === '1') {
            return;
        }

        form.dataset.bound = '1';
        form.addEventListener('submit', submitEditForm);
    }

    function bindModalClose() {
        document.querySelectorAll('[data-aa-tasks-modal-close="aa-task-list-edit-modal"]').forEach(function (button) {
            if (button.dataset.listEditCloseBound === '1') {
                return;
            }

            button.dataset.listEditCloseBound = '1';
            button.addEventListener('click', function () {
                closeModal('aa-task-list-edit-modal');
            });
        });
    }

    function initListEditModule() {
        bindEditDelegation();
        bindEditForm();
        bindModalClose();
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initListEditModule);
    } else {
        initListEditModule();
    }
})();

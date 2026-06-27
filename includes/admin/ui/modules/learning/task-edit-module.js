/**
 * Task Edit Module — modal de edición para tareas user editables (feed unified).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var isActionPending = false;
    var NOTES_MAX_LENGTH = 800;

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

        if (modalId === 'aa-task-edit-modal') {
            collapseEditFormOptions();
        }
    }

    function collapseEditFormOptions() {
        var options = document.getElementById('aa-task-edit-form-options');

        if (options) {
            options.open = false;
        }
    }

    function showFormError(message) {
        var errorEl = document.getElementById('aa-task-edit-form-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo guardar.';
        }
    }

    function clearFormError() {
        setVisible(document.getElementById('aa-task-edit-form-error'), false);
    }

    function normalizeDueAtInput(value) {
        var raw = value ? String(value).trim() : '';

        if (!raw) {
            return '';
        }

        if (raw.indexOf('T') !== -1) {
            var parts = raw.split('T');

            if (parts.length === 2) {
                var time = parts[1].length === 5 ? parts[1] + ':00' : parts[1];
                return parts[0] + ' ' + time;
            }
        }

        return raw;
    }

    function formatDueAtForInput(value) {
        var raw = value ? String(value).trim() : '';

        if (!raw) {
            return '';
        }

        if (raw.indexOf('T') !== -1) {
            return raw.length === 16 ? raw : raw.slice(0, 16);
        }

        return raw.replace(' ', 'T').slice(0, 16);
    }

    function padTwo(num) {
        return num < 10 ? '0' + num : String(num);
    }

    function todayMinForDatetimeLocal() {
        var now = new Date();

        return now.getFullYear()
            + '-'
            + padTwo(now.getMonth() + 1)
            + '-'
            + padTwo(now.getDate())
            + 'T00:00';
    }

    function applyTaskDueAtInputMin(dueInput) {
        if (dueInput) {
            dueInput.min = todayMinForDatetimeLocal();
        }
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
        var taskId = button.getAttribute('data-task-id') || '';
        var title = button.getAttribute('data-task-title') || '';
        var notes = button.getAttribute('data-task-notes') || '';
        var dueAt = button.getAttribute('data-task-due-at') || '';
        var importance = button.getAttribute('data-task-importance') || '0';
        var defaultBucket = button.getAttribute('data-task-default-bucket') || 'primary';

        if (!taskId) {
            return;
        }

        var taskIdInput = document.getElementById('aa-task-edit-form-task-id');
        var titleInput = document.getElementById('aa-task-edit-form-title');
        var notesInput = document.getElementById('aa-task-edit-form-notes');
        var dueInput = document.getElementById('aa-task-edit-form-due-at');
        var importanceInput = document.getElementById('aa-task-edit-form-importance');
        var bucketSelect = document.getElementById('aa-task-edit-form-default-bucket');

        if (taskIdInput) {
            taskIdInput.value = taskId;
        }

        if (titleInput) {
            titleInput.value = title;
        }

        if (notesInput) {
            notesInput.value = notes;
        }

        if (dueInput) {
            dueInput.value = formatDueAtForInput(dueAt);
            applyTaskDueAtInputMin(dueInput);
        }

        if (importanceInput) {
            importanceInput.value = importance;
        }

        if (bucketSelect) {
            bucketSelect.value = defaultBucket === 'secondary' ? 'secondary' : 'primary';
        }

        collapseEditFormOptions();
        clearFormError();
        openModal('aa-task-edit-modal');
    }

    function submitEditForm(event) {
        event.preventDefault();

        if (isActionPending) {
            return;
        }

        var service = getService();
        var taskIdInput = document.getElementById('aa-task-edit-form-task-id');
        var titleInput = document.getElementById('aa-task-edit-form-title');
        var notesInput = document.getElementById('aa-task-edit-form-notes');
        var dueInput = document.getElementById('aa-task-edit-form-due-at');
        var importanceInput = document.getElementById('aa-task-edit-form-importance');
        var bucketSelect = document.getElementById('aa-task-edit-form-default-bucket');
        var taskId = taskIdInput ? String(taskIdInput.value || '').trim() : '';
        var title = titleInput ? String(titleInput.value || '').trim() : '';
        var notes = notesInput ? String(notesInput.value || '') : '';

        if (!taskId) {
            showFormError('No se pudo identificar la tarea.');
            return;
        }

        if (!title) {
            showFormError('El título de la tarea es obligatorio.');
            return;
        }

        if (notes.trim().length > NOTES_MAX_LENGTH) {
            showFormError('Las notas no pueden superar ' + NOTES_MAX_LENGTH + ' caracteres.');
            return;
        }

        if (!service || typeof service.updateTask !== 'function') {
            showFormError('Servicio no disponible.');
            return;
        }

        isActionPending = true;
        clearFormError();

        var defaultBucket = bucketSelect ? String(bucketSelect.value || 'primary').trim() : 'primary';

        if (defaultBucket !== 'secondary') {
            defaultBucket = 'primary';
        }

        service.updateTask({
            task_id: taskId,
            title: title,
            notes: notes,
            due_at: normalizeDueAtInput(dueInput ? dueInput.value : ''),
            importance: importanceInput && importanceInput.value !== '' ? importanceInput.value : 0,
            default_bucket: defaultBucket
        })
            .then(function () {
                closeModal('aa-task-edit-modal');
                return reloadAfterMutation();
            })
            .catch(function (err) {
                showFormError((err && err.message) ? err.message : 'No se pudo actualizar la tarea.');
            })
            .finally(function () {
                isActionPending = false;
            });
    }

    function bindEditDelegation() {
        var root = document.getElementById('aa-tasks-module-root');

        if (!root || root.dataset.taskEditBound === '1') {
            return;
        }

        root.dataset.taskEditBound = '1';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-aa-task-edit]');

            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openEditModalFromButton(button);
        }, true);
    }

    function bindEditForm() {
        var form = document.getElementById('aa-task-edit-form');

        if (!form || form.dataset.bound === '1') {
            return;
        }

        form.dataset.bound = '1';
        form.addEventListener('submit', submitEditForm);
    }

    function bindModalClose() {
        document.querySelectorAll('[data-aa-tasks-modal-close="aa-task-edit-modal"]').forEach(function (button) {
            if (button.dataset.taskEditCloseBound === '1') {
                return;
            }

            button.dataset.taskEditCloseBound = '1';
            button.addEventListener('click', function () {
                closeModal('aa-task-edit-modal');
            });
        });
    }

    function initTaskEditModule() {
        bindEditDelegation();
        bindEditForm();
        bindModalClose();
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTaskEditModule);
    } else {
        initTaskEditModule();
    }
})();

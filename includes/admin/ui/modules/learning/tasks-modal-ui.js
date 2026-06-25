/**
 * Learning task/list modals — body scroll lock while a modal is open.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var MODAL_IDS = [
        'aa-task-modal',
        'aa-task-edit-modal',
        'aa-task-list-modal',
        'aa-task-list-edit-modal',
        'aa-restore-archived-lists-modal',
        'aa-restore-archived-tasks-modal'
    ];

    function isAnyLearningModalOpen() {
        return MODAL_IDS.some(function (id) {
            var modal = document.getElementById(id);

            return modal && !modal.classList.contains('hidden');
        });
    }

    function onLearningModalOpened() {
        document.body.classList.add('aa-modal-open');
    }

    function onLearningModalClosed() {
        if (!isAnyLearningModalOpen()) {
            document.body.classList.remove('aa-modal-open');
        }
    }

    var api = {
        onLearningModalOpened: onLearningModalOpened,
        onLearningModalClosed: onLearningModalClosed,
        isAnyLearningModalOpen: isAnyLearningModalOpen
    };

    globalRoot.AATasksModalUi = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();

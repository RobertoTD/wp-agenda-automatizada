/**
 * Tasks Service — Listas/Tareas (HTTP client).
 *
 * Depends on window.AA_TASKS_DATA (ajaxUrl, nonce).
 */
(function () {
    'use strict';

    function getConfig() {
        var cfg = window.AA_TASKS_DATA;

        if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
            return null;
        }

        return cfg;
    }

    /**
     * @param {string} action
     * @param {Object} [extraFields]
     * @returns {Promise<Object>}
     */
    function postAction(action, extraFields) {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_TASKS_DATA no configurado'));
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', cfg.nonce);

        if (extraFields) {
            Object.keys(extraFields).forEach(function (field) {
                var value = extraFields[field];

                if (value === undefined || value === null) {
                    return;
                }

                formData.append(field, String(value));
            });
        }

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    var payload = result.data || {};
                    var message = payload.message || 'No se pudo completar la acción.';
                    var err = new Error(message);
                    err.code = payload.code || 'unknown_error';
                    throw err;
                }

                return result.data || {};
            });
    }

    /**
     * @returns {Promise<{lists:Array,tasks:Array,organization:Object}>}
     */
    function getTaskBoard() {
        return postAction('aa_get_task_board').then(function (data) {
            return {
                lists: Array.isArray(data.lists) ? data.lists : [],
                tasks: Array.isArray(data.tasks) ? data.tasks : [],
                organization: data.organization && typeof data.organization === 'object'
                    ? data.organization
                    : {
                        list_order: [],
                        task_order_by_list: {},
                        executive_candidates: []
                    }
            };
        });
    }

    /**
     * @param {{title:string,description?:string,importance?:number}} payload
     * @returns {Promise<Object>}
     */
    function createTaskList(payload) {
        return postAction('aa_create_task_list', {
            title: payload.title,
            description: payload.description || '',
            importance: payload.importance !== undefined && payload.importance !== null
                ? payload.importance
                : 0
        });
    }

    /**
     * @param {{list_id:number|string,title:string,description?:string,importance?:number}} payload
     * @returns {Promise<Object>}
     */
    function updateTaskList(payload) {
        return postAction('aa_update_task_list', {
            list_id: payload.list_id,
            title: payload.title,
            description: payload.description || '',
            importance: payload.importance !== undefined && payload.importance !== null
                ? payload.importance
                : 0
        });
    }

    /**
     * @param {number|string} listId
     * @returns {Promise<Object>}
     */
    function archiveTaskList(listId) {
        return postAction('aa_archive_task_list', {
            list_id: listId
        });
    }

    /**
     * @param {number|string} listId
     * @returns {Promise<Object>}
     */
    function deleteTaskList(listId) {
        return postAction('aa_delete_task_list', {
            list_id: listId
        });
    }

    /**
     * @returns {Promise<{lists:Array}>}
     */
    function getArchivedTaskLists() {
        return postAction('aa_list_archived_task_lists').then(function (data) {
            return {
                lists: Array.isArray(data.lists) ? data.lists : []
            };
        });
    }

    /**
     * @param {number|string} listId
     * @returns {Promise<Object>}
     */
    function restoreTaskList(listId) {
        return postAction('aa_restore_task_list', {
            list_id: listId
        });
    }

    /**
     * @param {{list_id:number|string,title:string,notes?:string,due_at?:string,execution_available_at?:string,importance?:number,default_bucket?:'primary'|'secondary'}} payload
     * @returns {Promise<Object>}
     */
    function createTask(payload) {
        var fields = {
            list_id: payload.list_id,
            title: payload.title,
            notes: payload.notes || '',
            due_at: payload.due_at || '',
            execution_available_at: payload.execution_available_at || '',
            importance: payload.importance !== undefined && payload.importance !== null
                ? payload.importance
                : 0
        };

        if (payload.default_bucket) {
            fields.default_bucket = payload.default_bucket;
        }

        return postAction('aa_create_task', fields);
    }

    /**
     * @param {{task_id:number|string,title?:string,notes?:string,due_at?:string,execution_available_at?:string,importance?:number,position?:number,default_bucket?:'primary'|'secondary'}} payload
     * @returns {Promise<Object>}
     */
    function updateTask(payload) {
        var fields = {
            task_id: payload.task_id
        };

        if (payload.title !== undefined && payload.title !== null) {
            fields.title = payload.title;
        }

        if (payload.notes !== undefined && payload.notes !== null) {
            fields.notes = payload.notes;
        }

        if (payload.due_at !== undefined && payload.due_at !== null) {
            fields.due_at = payload.due_at;
        }

        if (payload.execution_available_at !== undefined && payload.execution_available_at !== null) {
            fields.execution_available_at = payload.execution_available_at;
        }

        if (payload.importance !== undefined && payload.importance !== null) {
            fields.importance = payload.importance;
        }

        if (payload.position !== undefined && payload.position !== null) {
            fields.position = payload.position;
        }

        if (payload.default_bucket === 'primary' || payload.default_bucket === 'secondary') {
            fields.default_bucket = payload.default_bucket;
        }

        return postAction('aa_update_task', fields);
    }

    /**
     * @param {number|string} taskId
     * @param {'pending'|'done'} status
     * @returns {Promise<Object>}
     */
    function changeTaskStatus(taskId, status) {
        return postAction('aa_change_task_status', {
            task_id: taskId,
            status: status
        });
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function deferTask(taskId) {
        return postAction('aa_defer_task', {
            task_id: taskId
        });
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function dismissTask(taskId) {
        return postAction('aa_dismiss_task', {
            task_id: taskId
        });
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function markTaskMissed(taskId) {
        return postAction('aa_mark_task_missed', {
            task_id: taskId
        });
    }

    /**
     * @returns {Promise<{returned_count:number,task_ids:Array}>}
     */
    function returnIgnoredUserTasks() {
        return postAction('aa_return_ignored_user_tasks');
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function archiveTask(taskId) {
        return postAction('aa_archive_task', {
            task_id: taskId
        });
    }

    /**
     * @param {number|string} listId
     * @returns {Promise<{tasks:Array}>}
     */
    function listArchivedTasksInList(listId) {
        return postAction('aa_list_archived_tasks_in_list', {
            list_id: listId
        }).then(function (data) {
            return {
                tasks: Array.isArray(data.tasks) ? data.tasks : []
            };
        });
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function restoreTask(taskId) {
        return postAction('aa_restore_task', {
            task_id: taskId
        });
    }

    /**
     * @param {number|string} taskId
     * @returns {Promise<Object>}
     */
    function deleteTask(taskId) {
        return postAction('aa_delete_task', {
            task_id: taskId
        });
    }

    /**
     * @param {string} deviceKey
     * @param {'prepared'|'unprepared'} readiness
     * @returns {Promise<Object>}
     */
    function reconcilePushActivationTask(deviceKey, readiness) {
        return postAction('aa_reconcile_push_activation_task', {
            device_key: deviceKey,
            readiness: readiness
        });
    }

    window.TasksService = {
        getTaskBoard: getTaskBoard,
        createTaskList: createTaskList,
        updateTaskList: updateTaskList,
        archiveTaskList: archiveTaskList,
        deleteTaskList: deleteTaskList,
        getArchivedTaskLists: getArchivedTaskLists,
        restoreTaskList: restoreTaskList,
        createTask: createTask,
        updateTask: updateTask,
        changeTaskStatus: changeTaskStatus,
        deferTask: deferTask,
        dismissTask: dismissTask,
        markTaskMissed: markTaskMissed,
        returnIgnoredUserTasks: returnIgnoredUserTasks,
        archiveTask: archiveTask,
        listArchivedTasksInList: listArchivedTasksInList,
        restoreTask: restoreTask,
        deleteTask: deleteTask,
        reconcilePushActivationTask: reconcilePushActivationTask
    };
})();

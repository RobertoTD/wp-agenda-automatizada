'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const servicePath = path.join(__dirname, '../../assets/js/services/tasksService.js');
const serviceSrc = fs.readFileSync(servicePath, 'utf8');

function createFormDataMock() {
    function FormDataMock() {
        this._fields = new Map();
    }

    FormDataMock.prototype.append = function (key, value) {
        this._fields.set(String(key), String(value));
    };

    FormDataMock.prototype.get = function (key) {
        return this._fields.has(String(key)) ? this._fields.get(String(key)) : null;
    };

    return FormDataMock;
}

function loadTasksService(fetchImpl) {
    var posts = [];

    var context = {
        window: {},
        fetch: fetchImpl || function (url, options) {
            posts.push({
                url: url,
                body: options && options.body ? options.body : null
            });

            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({ success: true, data: { task_state: { task_id: 1 } } });
                }
            });
        }
    };

    context.window = context;
    context.window.AA_TASKS_DATA = {
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        nonce: 'test-nonce'
    };
    context.FormData = createFormDataMock();

    vm.runInNewContext(serviceSrc, context, { filename: servicePath });

    return {
        TasksService: context.window.TasksService,
        posts: posts
    };
}

function readFormField(body, field) {
    if (!body || typeof body.get !== 'function') {
        return null;
    }

    return body.get(field);
}

describe('TasksService MC13G-C1', () => {
    let originalWindow;
    let originalFetch;

    beforeEach(() => {
        originalWindow = global.window;
        originalFetch = global.fetch;
        global.window = global;
        global.AA_TASKS_DATA = {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'test-nonce'
        };
    });

    afterEach(() => {
        if (originalWindow === undefined) {
            delete global.window;
        } else {
            global.window = originalWindow;
        }

        if (originalFetch === undefined) {
            delete global.fetch;
        } else {
            global.fetch = originalFetch;
        }

        delete global.AA_TASKS_DATA;
        delete global.TasksService;
    });

    it('deferTask postea aa_defer_task con task_id', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.deferTask(42);

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_defer_task');
        assert.equal(readFormField(posts[0].body, 'task_id'), '42');
        assert.equal(readFormField(posts[0].body, '_wpnonce'), 'test-nonce');
    });

    it('dismissTask postea aa_dismiss_task con task_id', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.dismissTask(99);

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_dismiss_task');
        assert.equal(readFormField(posts[0].body, 'task_id'), '99');
    });

    it('getArchivedTaskLists postea aa_list_archived_task_lists', async () => {
        var loaded = loadTasksService(function () {
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { lists: [{ id: 3, title: 'Archivada', status: 'archived' }] }
                    });
                }
            });
        });

        var result = await loaded.TasksService.getArchivedTaskLists();

        assert.equal(result.lists.length, 1);
        assert.equal(result.lists[0].id, 3);
    });

    it('restoreTaskList postea aa_restore_task_list con list_id', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.restoreTaskList(12);

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_restore_task_list');
        assert.equal(readFormField(posts[0].body, 'list_id'), '12');
        assert.equal(readFormField(posts[0].body, '_wpnonce'), 'test-nonce');
    });

    it('updateTaskList postea aa_update_task_list con list_id, title, description e importance', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.updateTaskList({
            list_id: 7,
            title: 'Lista actualizada',
            description: 'Nuevo objetivo',
            importance: 2
        });

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_update_task_list');
        assert.equal(readFormField(posts[0].body, 'list_id'), '7');
        assert.equal(readFormField(posts[0].body, 'title'), 'Lista actualizada');
        assert.equal(readFormField(posts[0].body, 'description'), 'Nuevo objetivo');
        assert.equal(readFormField(posts[0].body, 'importance'), '2');
        assert.equal(readFormField(posts[0].body, '_wpnonce'), 'test-nonce');
    });

    it('archiveTask postea aa_archive_task con task_id', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.archiveTask(24);

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_archive_task');
        assert.equal(readFormField(posts[0].body, 'task_id'), '24');
        assert.equal(readFormField(posts[0].body, '_wpnonce'), 'test-nonce');
    });

    it('returnIgnoredUserTasks postea aa_return_ignored_user_tasks', async () => {
        var loaded = loadTasksService();
        var posts = loaded.posts;

        await loaded.TasksService.returnIgnoredUserTasks();

        assert.equal(posts.length, 1);
        assert.equal(readFormField(posts[0].body, 'action'), 'aa_return_ignored_user_tasks');
        assert.equal(readFormField(posts[0].body, '_wpnonce'), 'test-nonce');
        assert.equal(readFormField(posts[0].body, 'task_id'), null);
    });
});

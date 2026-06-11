<?php
/**
 * Tasks AJAX — Listas/Tareas (transporte HTTP).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/tasks/GetTaskBoardUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/CreateTaskListUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/UpdateTaskListUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/ArchiveTaskListUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/ListArchivedTaskListsUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/RestoreTaskListUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/CreateTaskUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/UpdateTaskUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/ChangeTaskStatusUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/RecordTaskDeferSignalUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/RecordTaskDismissSignalUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/ReturnIgnoredUserTasksUseCase.php';

final class TasksAjax {

    private const NONCE_ACTION = 'aa_tasks_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_task_board', [__CLASS__, 'handle_get_board']);
        add_action('wp_ajax_aa_create_task_list', [__CLASS__, 'handle_create_list']);
        add_action('wp_ajax_aa_update_task_list', [__CLASS__, 'handle_update_list']);
        add_action('wp_ajax_aa_archive_task_list', [__CLASS__, 'handle_archive_list']);
        add_action('wp_ajax_aa_list_archived_task_lists', [__CLASS__, 'handle_list_archived_lists']);
        add_action('wp_ajax_aa_restore_task_list', [__CLASS__, 'handle_restore_list']);
        add_action('wp_ajax_aa_create_task', [__CLASS__, 'handle_create_task']);
        add_action('wp_ajax_aa_update_task', [__CLASS__, 'handle_update_task']);
        add_action('wp_ajax_aa_change_task_status', [__CLASS__, 'handle_change_status']);
        add_action('wp_ajax_aa_defer_task', [__CLASS__, 'handle_defer_task']);
        add_action('wp_ajax_aa_dismiss_task', [__CLASS__, 'handle_dismiss_task']);
        add_action('wp_ajax_aa_return_ignored_user_tasks', [__CLASS__, 'handle_return_ignored_user_tasks']);
    }

    public static function handle_get_board(): void {
        self::authorize();

        $result = (new GetTaskBoardUseCase())->execute();
        wp_send_json_success($result);
    }

    public static function handle_create_list(): void {
        self::authorize();

        $result = (new CreateTaskListUseCase())->execute([
            'title' => self::post_string('title'),
            'description' => self::post_string('description'),
            'importance' => self::post_scalar('importance'),
            'position' => self::post_scalar('position'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_update_list(): void {
        self::authorize();

        $input = self::collect_post_fields(['list_id', 'title', 'description', 'importance', 'position']);
        $result = (new UpdateTaskListUseCase())->execute($input);

        self::respond_use_case($result);
    }

    public static function handle_archive_list(): void {
        self::authorize();

        $result = (new ArchiveTaskListUseCase())->execute([
            'list_id' => self::post_scalar('list_id'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_list_archived_lists(): void {
        self::authorize();

        $result = (new ListArchivedTaskListsUseCase())->execute();

        self::respond_use_case($result);
    }

    public static function handle_restore_list(): void {
        self::authorize();

        $result = (new RestoreTaskListUseCase())->execute([
            'list_id' => self::post_scalar('list_id'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_create_task(): void {
        self::authorize();

        $input = [
            'list_id' => self::post_scalar('list_id'),
            'title' => self::post_string('title'),
            'notes' => self::post_string('notes'),
            'importance' => self::post_scalar('importance'),
            'due_at' => self::post_string('due_at'),
            'position' => self::post_scalar('position'),
        ];

        if (array_key_exists('default_bucket', $_POST)) {
            $input['default_bucket'] = self::post_string('default_bucket');
        }

        $result = (new CreateTaskUseCase())->execute($input);

        self::respond_use_case($result);
    }

    public static function handle_update_task(): void {
        self::authorize();

        $input = self::collect_post_fields(['task_id', 'title', 'notes', 'importance', 'due_at', 'position']);

        if (array_key_exists('default_bucket', $_POST)) {
            $input['default_bucket'] = self::post_string('default_bucket');
        }

        $result = (new UpdateTaskUseCase())->execute($input);

        self::respond_use_case($result);
    }

    public static function handle_change_status(): void {
        self::authorize();

        $result = (new ChangeTaskStatusUseCase())->execute([
            'task_id' => self::post_scalar('task_id'),
            'status' => self::post_string('status'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_defer_task(): void {
        self::authorize();

        $result = (new RecordTaskDeferSignalUseCase())->execute([
            'task_id' => self::post_scalar('task_id'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_dismiss_task(): void {
        self::authorize();

        $result = (new RecordTaskDismissSignalUseCase())->execute([
            'task_id' => self::post_scalar('task_id'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_return_ignored_user_tasks(): void {
        self::authorize();

        $result = (new ReturnIgnoredUserTasksUseCase())->execute();

        self::respond_use_case($result);
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond_use_case(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo completar la acción.'),
            'code' => (string) ($error['code'] ?? 'unknown_error'),
        ], 400);
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    private static function collect_post_fields(array $keys): array {
        $input = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            if (in_array($key, ['list_id', 'task_id', 'importance', 'position'], true)) {
                $input[$key] = self::post_scalar($key);
                continue;
            }

            $input[$key] = self::post_string($key);
        }

        return $input;
    }

    /**
     * @param string $key
     * @return string|null
     */
    private static function post_string($key): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }

        return sanitize_text_field(wp_unslash((string) $_POST[$key]));
    }

    /**
     * @param string $key
     * @return mixed
     */
    private static function post_scalar($key) {
        if (!isset($_POST[$key])) {
            return null;
        }

        return wp_unslash($_POST[$key]);
    }
}

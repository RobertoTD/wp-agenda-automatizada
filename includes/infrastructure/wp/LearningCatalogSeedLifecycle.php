<?php
/**
 * Learning Catalog Seed Lifecycle — sync controlado del catálogo Learning hacia DB común.
 *
 * Ejecuta SyncLearningCatalogToTasksUseCase en admin_init cuando la versión
 * de seed está desactualizada. Archived-first: la lista solo se activa tras validar
 * que el seed está completo.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/application/tasks/SyncLearningCatalogToTasksUseCase.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';

final class AA_Learning_Catalog_Seed_Lifecycle {

    public const OPTION_SEED_VERSION = 'aa_learning_catalog_seed_version';

    public const OPTION_LAST_ERROR = 'aa_learning_catalog_seed_last_error';

    public const OPTION_LAST_SYNCED_AT = 'aa_learning_catalog_seed_last_synced_at';

    public const LOCK_KEY = 'aa_learning_catalog_seed_lock';

    public const LOCK_TTL_SECONDS = 60;

    private const MIN_DB_VERSION = '7';

    private const SOURCE_CATEGORY = 'agenda_app';

    private const LIST_ORIGIN_KEY = 'learning.recommendations';

    /** @var callable|null Override for acceptance tests only. */
    private static $sync_executor_override = null;

    /** @var callable|null Override for acceptance tests only. */
    private static $activate_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_sync'], 20);
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $executor Debe devolver el payload de SyncLearningCatalogToTasksUseCase::execute().
     */
    public static function set_sync_executor_for_tests(?callable $executor): void {
        self::$sync_executor_override = $executor;
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $override Debe devolver bool (éxito de activación).
     */
    public static function set_activate_override_for_tests(?callable $override): void {
        self::$activate_override = $override;
    }

    public static function maybe_sync(): void {
        if (self::should_skip_sync()) {
            return;
        }

        if (!self::acquire_lock()) {
            return;
        }

        try {
            $result = self::run_sync();
            $expected_task_count = count(AA_Learning_Catalog::active_definition_keys());
            $actual_task_count = is_array($result['task_ids'] ?? null) ? count($result['task_ids']) : 0;
            $list_id = (int) ($result['list_id'] ?? 0);

            if ($list_id < 1 || $actual_task_count !== $expected_task_count) {
                $message = sprintf(
                    'Learning catalog seed incomplete (list_id=%d, tasks=%d, expected=%d).',
                    $list_id,
                    $actual_task_count,
                    $expected_task_count
                );
                error_log('[AA_Learning_Catalog_Seed_Lifecycle] ' . $message);
                update_option(self::OPTION_LAST_ERROR, $message);

                return;
            }

            if (!self::activate_seeded_list()) {
                $message = 'Learning catalog seed could not activate seeded list.';
                error_log('[AA_Learning_Catalog_Seed_Lifecycle] ' . $message);
                update_option(self::OPTION_LAST_ERROR, $message);

                return;
            }

            update_option(self::OPTION_SEED_VERSION, AA_Learning_Catalog::SEED_VERSION);
            update_option(self::OPTION_LAST_SYNCED_AT, current_time('mysql'));
            delete_option(self::OPTION_LAST_ERROR);
        } catch (\Throwable $exception) {
            error_log('[AA_Learning_Catalog_Seed_Lifecycle] Sync failed: ' . $exception->getMessage());
            update_option(self::OPTION_LAST_ERROR, $exception->getMessage());
        } finally {
            self::release_lock();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function run_sync(): array {
        if (self::$sync_executor_override !== null) {
            $result = call_user_func(self::$sync_executor_override);

            return is_array($result) ? $result : [];
        }

        return (new SyncLearningCatalogToTasksUseCase())->execute();
    }

    private static function should_skip_sync(): bool {
        if (wp_doing_ajax()) {
            return true;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        $db_version = (string) get_option('aa_db_version', '0');

        if (version_compare($db_version, self::MIN_DB_VERSION, '<')) {
            return true;
        }

        $stored_seed_version = (string) get_option(self::OPTION_SEED_VERSION, '0');

        return version_compare($stored_seed_version, AA_Learning_Catalog::SEED_VERSION, '>=');
    }

    private static function acquire_lock(): bool {
        if (get_transient(self::LOCK_KEY)) {
            return false;
        }

        return set_transient(self::LOCK_KEY, '1', self::LOCK_TTL_SECONDS);
    }

    private static function release_lock(): void {
        delete_transient(self::LOCK_KEY);
    }

    private static function activate_seeded_list(): bool {
        if (self::$activate_override !== null) {
            return (bool) call_user_func(self::$activate_override);
        }

        $list = SeededTaskRepository::upsert_seeded_list([
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => self::LIST_ORIGIN_KEY,
            'status' => 'active',
        ]);

        return $list !== null && strtolower(trim((string) ($list['status'] ?? ''))) === 'active';
    }
}

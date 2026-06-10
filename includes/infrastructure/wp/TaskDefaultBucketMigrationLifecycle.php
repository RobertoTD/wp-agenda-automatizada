<?php
/**
 * Task Default Bucket Migration Lifecycle — backfill defer histórico → default_bucket (MC13O-H3B-2).
 *
 * Ejecuta MigrateDeferredTasksToDefaultBucketUseCase en admin_init prioridad 22,
 * después del seed del catálogo (20) y la migración Learning state (21).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/application/tasks/MigrateDeferredTasksToDefaultBucketUseCase.php';
require_once __DIR__ . '/LearningCatalogSeedLifecycle.php';

final class AA_Task_Default_Bucket_Migration_Lifecycle {

    public const MIGRATION_VERSION = '1';

    public const OPTION_MIGRATION_VERSION = 'aa_task_default_bucket_migration_version';

    public const OPTION_LAST_ERROR = 'aa_task_default_bucket_migration_last_error';

    public const OPTION_LAST_RUN_AT = 'aa_task_default_bucket_migration_last_run_at';

    public const LOCK_KEY = 'aa_task_default_bucket_migration_lock';

    public const LOCK_TTL_SECONDS = 60;

    private const MIN_DB_VERSION = '7';

    /** @var callable|null Override for acceptance tests only. */
    private static $migration_executor_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_migrate'], 22);
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $executor Debe devolver el payload de MigrateDeferredTasksToDefaultBucketUseCase::execute().
     */
    public static function set_migration_executor_for_tests(?callable $executor): void {
        self::$migration_executor_override = $executor;
    }

    public static function maybe_migrate(): void {
        if (self::should_skip_migration()) {
            return;
        }

        if (!self::acquire_lock()) {
            return;
        }

        try {
            $result = self::run_migration();

            if (empty($result['success'])) {
                $message = (string) ($result['error']['message'] ?? 'task default bucket migration failed');
                error_log('[AA_Task_Default_Bucket_Migration_Lifecycle] ' . $message);
                update_option(self::OPTION_LAST_ERROR, $message);

                return;
            }

            update_option(self::OPTION_MIGRATION_VERSION, self::MIGRATION_VERSION);
            update_option(self::OPTION_LAST_RUN_AT, current_time('mysql'));
            delete_option(self::OPTION_LAST_ERROR);
        } catch (\Throwable $exception) {
            error_log('[AA_Task_Default_Bucket_Migration_Lifecycle] Migration failed: ' . $exception->getMessage());
            update_option(self::OPTION_LAST_ERROR, $exception->getMessage());
        } finally {
            self::release_lock();
        }
    }

    /**
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    private static function run_migration(): array {
        if (self::$migration_executor_override !== null) {
            $result = call_user_func(self::$migration_executor_override);

            return is_array($result) ? $result : [
                'success' => false,
                'error' => [
                    'code' => 'invalid_migration_result',
                    'message' => 'Migration executor must return an array.',
                ],
            ];
        }

        return (new MigrateDeferredTasksToDefaultBucketUseCase())->execute();
    }

    private static function should_skip_migration(): bool {
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

        $stored_seed_version = (string) get_option(AA_Learning_Catalog_Seed_Lifecycle::OPTION_SEED_VERSION, '0');

        if (version_compare($stored_seed_version, AA_Learning_Catalog::SEED_VERSION, '<')) {
            return true;
        }

        $stored_migration_version = (string) get_option(self::OPTION_MIGRATION_VERSION, '0');

        return version_compare($stored_migration_version, self::MIGRATION_VERSION, '>=');
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
}

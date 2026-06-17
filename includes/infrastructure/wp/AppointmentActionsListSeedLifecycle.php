<?php
/**
 * Appointment Actions List Seed Lifecycle — sync de la lista appointment_actions.
 *
 * Ejecuta SyncAppointmentActionsListUseCase en admin_init cuando la versión
 * de seed está desactualizada.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once dirname(__DIR__, 2) . '/application/tasks/SyncAppointmentActionsListUseCase.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';

final class AA_Appointment_Actions_List_Seed_Lifecycle {

    public const OPTION_SEED_VERSION = 'aa_appointment_actions_list_seed_version';

    public const OPTION_LAST_ERROR = 'aa_appointment_actions_list_seed_last_error';

    public const OPTION_LAST_SYNCED_AT = 'aa_appointment_actions_list_seed_last_synced_at';

    public const LOCK_KEY = 'aa_appointment_actions_list_seed_lock';

    public const LOCK_TTL_SECONDS = 60;

    private const MIN_DB_VERSION = '7';

    /** @var callable|null Override for acceptance tests only. */
    private static $sync_executor_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_sync'], 20);
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $executor Debe devolver el payload de SyncAppointmentActionsListUseCase::execute().
     */
    public static function set_sync_executor_for_tests(?callable $executor): void {
        self::$sync_executor_override = $executor;
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
            $list_id = (int) ($result['list_id'] ?? 0);

            if ($list_id < 1 || !self::seeded_list_is_active()) {
                $message = sprintf(
                    'Appointment actions list seed incomplete (list_id=%d).',
                    $list_id
                );
                error_log('[AA_Appointment_Actions_List_Seed_Lifecycle] ' . $message);
                update_option(self::OPTION_LAST_ERROR, $message);

                return;
            }

            update_option(self::OPTION_SEED_VERSION, AA_Appointment_Actions_Catalog::SEED_VERSION);
            update_option(self::OPTION_LAST_SYNCED_AT, current_time('mysql'));
            delete_option(self::OPTION_LAST_ERROR);
        } catch (\Throwable $exception) {
            error_log('[AA_Appointment_Actions_List_Seed_Lifecycle] Sync failed: ' . $exception->getMessage());
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

        return (new SyncAppointmentActionsListUseCase())->execute();
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

        return version_compare($stored_seed_version, AA_Appointment_Actions_Catalog::SEED_VERSION, '>=');
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

    private static function seeded_list_is_active(): bool {
        $list = SeededTaskRepository::find_list_by_origin(
            AA_Appointment_Actions_Catalog::SOURCE_CATEGORY,
            AA_Appointment_Actions_Catalog::LIST_ORIGIN_KEY
        );

        return $list !== null
            && strtolower(trim((string) ($list['status'] ?? ''))) === 'active';
    }
}

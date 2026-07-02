<?php
/**
 * Initial Setup Seed Lifecycle — seed de Cliente de Prueba en agendas nuevas elegibles.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-setup-seed-definition.php';
require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-seed-eligibility-policy.php';
require_once dirname(__DIR__, 2) . '/application/setup/SeedInitialSetupClientUseCase.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once __DIR__ . '/InitialSeedEligibilityLifecycle.php';

final class AA_Initial_Setup_Seed_Lifecycle {

    public const OPTION_SEED_VERSION = 'aa_initial_setup_seed_version';

    public const OPTION_COMPLETED_AT = 'aa_initial_setup_seed_completed_at';

    public const OPTION_LAST_ERROR = 'aa_initial_setup_seed_last_error';

    public const LOCK_KEY = 'aa_initial_setup_seed_lock';

    public const LOCK_TTL_SECONDS = 60;

    /** @var callable|null Override for acceptance tests only. */
    private static $seed_executor_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_seed'], 20);
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $executor Debe devolver el payload de SeedInitialSetupClientUseCase::execute().
     */
    public static function set_seed_executor_for_tests(?callable $executor): void {
        self::$seed_executor_override = $executor;
    }

    public static function maybe_seed(): void {
        if (self::should_skip_seed()) {
            return;
        }

        if (!self::acquire_lock()) {
            return;
        }

        try {
            if (get_option(AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY, false) === false) {
                return;
            }

            $eligibility = (string) get_option(AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY, '');

            if ($eligibility !== AA_Initial_Seed_Eligibility_Policy::ELIGIBLE) {
                self::mark_seed_complete('skipped_ineligible');

                return;
            }

            if (ClientsRepository::count_registered_clients() > 0) {
                self::mark_seed_complete('skipped_existing_clients');

                return;
            }

            if (ClientsRepository::find_by_telefono(AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL) !== null) {
                self::mark_seed_complete('skipped_existing_seed_phone');

                return;
            }

            $result = self::run_seed();

            if (($result['status'] ?? '') === 'created' || ($result['status'] ?? '') === 'already_exists') {
                delete_option(self::OPTION_LAST_ERROR);
                self::mark_seed_complete((string) ($result['status'] ?? 'completed'));

                return;
            }

            $message = (string) ($result['message'] ?? 'Initial setup client seed failed.');
            error_log('[AA_Initial_Setup_Seed_Lifecycle] ' . $message);
            update_option(self::OPTION_LAST_ERROR, $message);
        } catch (\Throwable $exception) {
            error_log('[AA_Initial_Setup_Seed_Lifecycle] Seed failed: ' . $exception->getMessage());
            update_option(self::OPTION_LAST_ERROR, $exception->getMessage());
        } finally {
            self::release_lock();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function run_seed(): array {
        if (self::$seed_executor_override !== null) {
            $result = call_user_func(self::$seed_executor_override);

            return is_array($result) ? $result : [];
        }

        return (new SeedInitialSetupClientUseCase())->execute();
    }

    private static function should_skip_seed(): bool {
        if (wp_doing_ajax()) {
            return true;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        $stored_seed_version = (string) get_option(self::OPTION_SEED_VERSION, '0');

        return version_compare($stored_seed_version, AA_Initial_Setup_Seed_Definition::SEED_VERSION, '>=');
    }

    private static function mark_seed_complete(string $reason = 'completed'): void {
        update_option(self::OPTION_SEED_VERSION, AA_Initial_Setup_Seed_Definition::SEED_VERSION);
        update_option(self::OPTION_COMPLETED_AT, current_time('mysql'));
        delete_option(self::OPTION_LAST_ERROR);

        if ($reason !== 'completed' && $reason !== 'created' && $reason !== 'already_exists') {
            error_log('[AA_Initial_Setup_Seed_Lifecycle] Seed marked complete: ' . $reason);
        }
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

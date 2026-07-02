<?php
/**
 * Initial Seed Eligibility Lifecycle — evalúa elegibilidad una sola vez en admin_init.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-seed-eligibility-policy.php';
require_once dirname(__DIR__, 2) . '/infrastructure/wp/Schema.php';

final class AA_Initial_Seed_Eligibility_Lifecycle {

    public const OPTION_ELIGIBILITY = 'aa_initial_seed_eligibility';

    /** @var callable|null Override for acceptance tests only. */
    private static $facts_collector_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_evaluate'], 19);
    }

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $override Debe devolver el array de facts para la policy.
     */
    public static function set_facts_collector_for_tests(?callable $override): void {
        self::$facts_collector_override = $override;
    }

    public static function maybe_evaluate(): void {
        if (self::should_skip()) {
            return;
        }

        $facts = self::collect_facts();
        $eligibility = (new AA_Initial_Seed_Eligibility_Policy())->evaluate($facts);

        add_option(self::OPTION_ELIGIBILITY, $eligibility);
    }

    /**
     * @return array<string,mixed>
     */
    public static function collect_facts(): array {
        if (self::$facts_collector_override !== null) {
            $facts = call_user_func(self::$facts_collector_override);

            return is_array($facts) ? $facts : [];
        }

        require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
        require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
        require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';

        $initialized_at = get_option(AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT, '');

        return [
            'has_installation_initialized_at' => is_string($initialized_at) && trim($initialized_at) !== '',
            'registered_client_count' => ClientsRepository::count_registered_clients(),
            'active_service_count' => AssignmentsRepository::count_active_services(),
            'active_staff_count' => AssignmentsRepository::count_active_staff(),
            'active_area_count' => AssignmentsRepository::count_active_service_areas(),
            'created_reservation_count' => ReservationsRepository::count_created_reservations(),
        ];
    }

    private static function should_skip(): bool {
        if (get_option(self::OPTION_ELIGIBILITY, false) !== false) {
            return true;
        }

        if (wp_doing_ajax()) {
            return true;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        return false;
    }
}

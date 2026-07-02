<?php
/**
 * AC MC1 — Initial setup seed (Cliente de Prueba).
 *
 * Ejecutar: php tests/infrastructure/wp/test-initial-setup-seed-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$root = dirname(__DIR__, 3);

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_transients'] = [];
$GLOBALS['aa_test_doing_ajax'] = false;
$GLOBALS['aa_test_doing_cron'] = false;
$GLOBALS['aa_test_wpdb_rows'] = [];
$GLOBALS['aa_test_wpdb_insert_id'] = 0;
$GLOBALS['aa_test_repository_counts'] = [
    'registered_client_count' => 0,
    'active_service_count' => 0,
    'active_staff_count' => 0,
    'active_area_count' => 0,
    'created_reservation_count' => 0,
];
$GLOBALS['aa_test_seed_calls'] = 0;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['aa_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($key, $value) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return false;
        }

        $GLOBALS['aa_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['aa_test_options'][$key]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!array_key_exists($key, $GLOBALS['aa_test_transients'])) {
            return false;
        }

        $entry = $GLOBALS['aa_test_transients'][$key];

        if (($entry['expires_at'] ?? 0) < time()) {
            unset($GLOBALS['aa_test_transients'][$key]);

            return false;
        }

        return $entry['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        $GLOBALS['aa_test_transients'][$key] = [
            'value' => $value,
            'expires_at' => time() + (int) $expiration,
        ];

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['aa_test_transients'][$key]);

        return true;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool {
        return (bool) $GLOBALS['aa_test_doing_ajax'];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-07-02 12:00:00' : '2026-07-02 12:00:00';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        $GLOBALS['aa_test_registered_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => (int) $priority,
        ];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return is_string($value) ? trim($value) : '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        $value = is_string($value) ? trim($value) : '';

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;

        public function __construct($code, $message) {
            $this->message = $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

class AA_Test_WPDB_Mock {
    public $prefix = 'wp_';

    public $last_error = '';

    public $insert_id = 0;

    public function prepare($query, ...$args) {
        if ($args === []) {
            return $query;
        }

        $index = 0;

        return preg_replace_callback('/%[sd]/', static function () use (&$index, $args) {
            $value = $args[$index++] ?? '';

            return is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
        }, $query);
    }

    public function get_var($query) {
        if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'aa_clientes') !== false) {
            return (string) count($GLOBALS['aa_test_wpdb_rows']);
        }

        return '0';
    }

    public function get_row($query, $output = OBJECT) {
        if (stripos($query, 'aa_clientes') === false || stripos($query, 'telefono') === false) {
            return null;
        }

        if (preg_match("/telefono = '([^']+)'/", $query, $matches) !== 1) {
            return null;
        }

        $phone = $matches[1];

        foreach ($GLOBALS['aa_test_wpdb_rows'] as $row) {
            if (($row['telefono'] ?? '') === $phone) {
                return $output === ARRAY_A ? $row : (object) $row;
            }
        }

        return null;
    }

    public function insert($table, $data, $format = null) {
        $GLOBALS['aa_test_wpdb_insert_id']++;
        $row = $data;
        $row['id'] = $GLOBALS['aa_test_wpdb_insert_id'];
        $GLOBALS['aa_test_wpdb_rows'][] = $row;
        $this->insert_id = $GLOBALS['aa_test_wpdb_insert_id'];

        return 1;
    }
}

$GLOBALS['wpdb'] = new AA_Test_WPDB_Mock();

require_once $root . '/includes/domain/setup/class-aa-initial-setup-seed-definition.php';
require_once $root . '/includes/domain/setup/class-aa-initial-seed-eligibility-policy.php';
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/domain/setup/class-aa-initial-setup-seed-owner-email-resolver.php';
require_once $root . '/includes/repositories/ClientsRepository.php';
require_once $root . '/includes/application/setup/SeedInitialSetupClientUseCase.php';
require_once $root . '/includes/infrastructure/wp/Schema.php';
require_once $root . '/includes/infrastructure/wp/InitialSeedEligibilityLifecycle.php';
require_once $root . '/includes/infrastructure/wp/InitialSetupSeedLifecycle.php';
require_once $root . '/includes/domain/onboarding/class-aa-onboarding-activation-policy.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

function seed_reset_state(): void {
    $GLOBALS['aa_test_options'] = [];
    $GLOBALS['aa_test_transients'] = [];
    $GLOBALS['aa_test_doing_ajax'] = false;
    $GLOBALS['aa_test_doing_cron'] = false;
    $GLOBALS['aa_test_wpdb_rows'] = [];
    $GLOBALS['aa_test_wpdb_insert_id'] = 0;
    $GLOBALS['aa_test_seed_calls'] = 0;
    AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(null);
    AA_Initial_Seed_Eligibility_Lifecycle::set_facts_collector_for_tests(null);
}

function seed_set_default_facts_collector(): void {
    AA_Initial_Seed_Eligibility_Lifecycle::set_facts_collector_for_tests(static function (): array {
        $initialized_at = get_option(AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT, '');

        return [
            'has_installation_initialized_at' => is_string($initialized_at) && trim($initialized_at) !== '',
            'registered_client_count' => count($GLOBALS['aa_test_wpdb_rows']),
            'active_service_count' => 0,
            'active_staff_count' => 0,
            'active_area_count' => 0,
            'created_reservation_count' => 0,
        ];
    });
}

function seed_run_eligibility_and_seed(): void {
    seed_set_default_facts_collector();
    AA_Initial_Seed_Eligibility_Lifecycle::maybe_evaluate();
    AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
}

// --- Policy ---

$policy = new AA_Initial_Seed_Eligibility_Policy();
ac_assert(
    'Policy eligible on fresh empty installation',
    $policy->evaluate([
        'has_installation_initialized_at' => true,
        'registered_client_count' => 0,
        'active_service_count' => 0,
        'active_staff_count' => 0,
        'active_area_count' => 0,
        'created_reservation_count' => 0,
    ]) === AA_Initial_Seed_Eligibility_Policy::ELIGIBLE
);
ac_assert(
    'Policy ineligible without installation_initialized_at',
    $policy->evaluate([
        'has_installation_initialized_at' => false,
        'registered_client_count' => 0,
        'active_service_count' => 0,
        'active_staff_count' => 0,
        'active_area_count' => 0,
        'created_reservation_count' => 0,
    ]) === AA_Initial_Seed_Eligibility_Policy::INELIGIBLE
);
ac_assert(
    'Policy ineligible when clients already exist',
    $policy->evaluate([
        'has_installation_initialized_at' => true,
        'registered_client_count' => 1,
        'active_service_count' => 0,
        'active_staff_count' => 0,
        'active_area_count' => 0,
        'created_reservation_count' => 0,
    ]) === AA_Initial_Seed_Eligibility_Policy::INELIGIBLE
);

// --- Email resolver ---

seed_reset_state();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['deoia_owner_email'] = 'owner@agenda.test';
$GLOBALS['aa_test_options']['admin_email'] = 'admin@site.test';
ac_assert(
    'Email resolver uses deoia_owner_email when provisioned',
    AA_Initial_Setup_Seed_Owner_Email_Resolver::resolve() === 'owner@agenda.test'
);

seed_reset_state();
$GLOBALS['aa_test_options']['admin_email'] = 'standalone@site.test';
ac_assert(
    'Email resolver uses admin_email on standalone install',
    AA_Initial_Setup_Seed_Owner_Email_Resolver::resolve() === 'standalone@site.test'
);

seed_reset_state();
ac_assert(
    'Email resolver falls back to empty string',
    AA_Initial_Setup_Seed_Owner_Email_Resolver::resolve() === ''
);

// --- AC1: Agenda nueva provisionada ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['deoia_owner_email'] = 'owner@agenda.test';
seed_run_eligibility_and_seed();
$row = $GLOBALS['aa_test_wpdb_rows'][0] ?? [];
ac_assert('AC1 eligible', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'eligible');
ac_assert('AC1 creates one client', count($GLOBALS['aa_test_wpdb_rows']) === 1);
ac_assert('AC1 client name', ($row['nombre'] ?? '') === AA_Initial_Setup_Seed_Definition::CLIENT_NAME);
ac_assert('AC1 client phone canonical', ($row['telefono'] ?? '') === AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL);
ac_assert('AC1 client email from deoia_owner_email', ($row['correo'] ?? '') === 'owner@agenda.test');
ac_assert(
    'AC1 seed version marked',
    ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === AA_Initial_Setup_Seed_Definition::SEED_VERSION
);

// --- AC2: WordPress independiente nuevo ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['admin_email'] = 'standalone@site.test';
seed_run_eligibility_and_seed();
$row = $GLOBALS['aa_test_wpdb_rows'][0] ?? [];
ac_assert('AC2 eligible', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'eligible');
ac_assert('AC2 creates one client', count($GLOBALS['aa_test_wpdb_rows']) === 1);
ac_assert('AC2 client email from admin_email', ($row['correo'] ?? '') === 'standalone@site.test');

// --- AC3: Reactivación no duplica ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] = 'eligible';
$GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] = AA_Initial_Setup_Seed_Definition::SEED_VERSION;
$GLOBALS['aa_test_wpdb_rows'][] = [
    'id' => 1,
    'nombre' => AA_Initial_Setup_Seed_Definition::CLIENT_NAME,
    'telefono' => AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL,
    'correo' => 'owner@agenda.test',
];
AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_seed_calls']++;

    return ['status' => 'created', 'client_id' => 99];
});
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('AC3 reactivation does not call seed executor', (int) $GLOBALS['aa_test_seed_calls'] === 0);
ac_assert('AC3 row count unchanged', count($GLOBALS['aa_test_wpdb_rows']) === 1);

// --- AC4: Agenda pre-MC1 ---

seed_reset_state();
seed_run_eligibility_and_seed();
ac_assert('AC4 ineligible without installation_initialized_at', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'ineligible');
ac_assert('AC4 does not create client', count($GLOBALS['aa_test_wpdb_rows']) === 0);
ac_assert(
    'AC4 still marks seed version',
    ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === AA_Initial_Setup_Seed_Definition::SEED_VERSION
);

// --- AC5: Agenda con cliente real ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_wpdb_rows'][] = [
    'id' => 10,
    'nombre' => 'Cliente Real',
    'telefono' => '521111111111',
    'correo' => 'real@client.test',
];
seed_run_eligibility_and_seed();
ac_assert('AC5 ineligible with existing client', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'ineligible');
ac_assert('AC5 does not create seed client', count($GLOBALS['aa_test_wpdb_rows']) === 1);
ac_assert(
    'AC5 marks seed version',
    ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === AA_Initial_Setup_Seed_Definition::SEED_VERSION
);

// --- AC6: Onboarding client step complete ---

$onboarding = (new AA_Onboarding_Activation_Policy())->evaluate([
    'registered_client_count' => 1,
    'active_service_count' => 0,
    'active_staff_count' => 0,
    'active_staff_with_active_service_count' => 0,
    'active_area_count' => 0,
    'created_reservation_count' => 0,
]);
ac_assert('AC6 onboarding client step complete', ($onboarding['steps']['client']['completed'] ?? false) === true);
ac_assert('AC6 next step is service', ($onboarding['next_step'] ?? null) === 'service');

// --- Wiring / guards ---

$bootstrap_src = file_get_contents($root . '/wp-agenda-automatizada.php');
ac_assert('Bootstrap requires InitialSetupSeedLifecycle', strpos($bootstrap_src, 'InitialSetupSeedLifecycle.php') !== false);
ac_assert('Bootstrap registers initial setup seed lifecycle', strpos($bootstrap_src, 'AA_Initial_Setup_Seed_Lifecycle::register') !== false);
ac_assert('Bootstrap registers eligibility lifecycle', strpos($bootstrap_src, 'AA_Initial_Seed_Eligibility_Lifecycle::register') !== false);

$schema_src = file_get_contents($root . '/includes/infrastructure/wp/Schema.php');
ac_assert('Schema sets installation_initialized_at on fresh install', strpos($schema_src, 'OPTION_INSTALLATION_INITIALIZED_AT') !== false);

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_seed_calls']++;

    return ['status' => 'created', 'client_id' => 1];
});
$GLOBALS['aa_test_doing_ajax'] = true;
AA_Initial_Seed_Eligibility_Lifecycle::maybe_evaluate();
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('Seed skips during AJAX', (int) $GLOBALS['aa_test_seed_calls'] === 0);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);

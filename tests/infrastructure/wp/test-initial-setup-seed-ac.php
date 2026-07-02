<?php
/**
 * AC — Initial setup seed v2 (Cliente, Servicio, Personal, Zona de prueba).
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
$GLOBALS['aa_test_wpdb_insert_id'] = 0;
$GLOBALS['aa_test_seed_calls'] = 0;
$GLOBALS['aa_test_clients'] = [];
$GLOBALS['aa_test_services'] = [];
$GLOBALS['aa_test_staff'] = [];
$GLOBALS['aa_test_areas'] = [];
$GLOBALS['aa_test_staff_services'] = [];
$GLOBALS['aa_test_force_staff_create_error'] = false;

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

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

    private function table_key(string $table): string {
        if (strpos($table, 'aa_clientes') !== false) {
            return 'clients';
        }
        if (strpos($table, 'aa_services') !== false) {
            return 'services';
        }
        if (strpos($table, 'aa_staff_services') !== false) {
            return 'staff_services';
        }
        if (strpos($table, 'aa_staff') !== false) {
            return 'staff';
        }
        if (strpos($table, 'aa_service_areas') !== false) {
            return 'areas';
        }

        return 'unknown';
    }

    private function &bucket(string $key) {
        switch ($key) {
            case 'clients':
                return $GLOBALS['aa_test_clients'];
            case 'services':
                return $GLOBALS['aa_test_services'];
            case 'staff':
                return $GLOBALS['aa_test_staff'];
            case 'areas':
                return $GLOBALS['aa_test_areas'];
            case 'staff_services':
                return $GLOBALS['aa_test_staff_services'];
            default:
                $empty = [];

                return $empty;
        }
    }

    public function get_var($query) {
        $this->last_error = '';

        if (stripos($query, 'COUNT(DISTINCT st.id)') !== false) {
            $staff = $this->bucket('staff');
            $services = $this->bucket('services');
            $links = $this->bucket('staff_services');
            $count = 0;

            foreach ($staff as $member) {
                if ((int) ($member['active'] ?? 0) !== 1 || (int) ($member['is_hidden'] ?? 0) !== 0) {
                    continue;
                }

                foreach ($links as $link) {
                    if ((int) ($link['staff_id'] ?? 0) !== (int) ($member['id'] ?? 0)) {
                        continue;
                    }

                    foreach ($services as $service) {
                        if ((int) ($service['id'] ?? 0) === (int) ($link['service_id'] ?? 0)
                            && (int) ($service['active'] ?? 0) === 1
                            && (int) ($service['is_hidden'] ?? 0) === 0
                        ) {
                            $count++;
                            break 2;
                        }
                    }
                }
            }

            return (string) $count;
        }

        if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'aa_clientes') !== false) {
            return (string) count($GLOBALS['aa_test_clients']);
        }

        if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'aa_services') !== false) {
            return (string) count(array_filter($GLOBALS['aa_test_services'], static function ($row) {
                return (int) ($row['active'] ?? 0) === 1 && (int) ($row['is_hidden'] ?? 0) === 0;
            }));
        }

        if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'aa_staff') !== false) {
            return (string) count(array_filter($GLOBALS['aa_test_staff'], static function ($row) {
                return (int) ($row['active'] ?? 0) === 1 && (int) ($row['is_hidden'] ?? 0) === 0;
            }));
        }

        if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'aa_service_areas') !== false) {
            return (string) count(array_filter($GLOBALS['aa_test_areas'], static function ($row) {
                return (int) ($row['active'] ?? 0) === 1 && (int) ($row['is_hidden'] ?? 0) === 0;
            }));
        }

        if (stripos($query, 'aa_service_areas') !== false && stripos($query, 'SELECT id') !== false) {
            foreach ($GLOBALS['aa_test_areas'] as $area) {
                if ((int) ($area['active'] ?? 0) === 1 && (int) ($area['is_hidden'] ?? 0) === 0) {
                    return (string) ($area['id'] ?? 0);
                }
            }

            return null;
        }

        return '0';
    }

    public function get_col($query) {
        $this->last_error = '';

        if (stripos($query, 'aa_staff_services') !== false && stripos($query, 'service_id') !== false) {
            if (preg_match('/staff_id = (\d+)/', $query, $matches) !== 1) {
                return [];
            }

            $staff_id = (int) $matches[1];
            $ids = [];

            foreach ($GLOBALS['aa_test_staff_services'] as $link) {
                if ((int) ($link['staff_id'] ?? 0) === $staff_id) {
                    $ids[] = (string) ($link['service_id'] ?? 0);
                }
            }

            return $ids;
        }

        if (stripos($query, 'aa_staff') !== false && stripos($query, 'SELECT id') !== false) {
            $ids = [];

            foreach ($GLOBALS['aa_test_staff'] as $row) {
                if ((int) ($row['active'] ?? 0) === 1 && (int) ($row['is_hidden'] ?? 0) === 0) {
                    $ids[] = (string) ($row['id'] ?? 0);
                }
            }

            return $ids;
        }

        if (stripos($query, 'aa_services') !== false && stripos($query, 'SELECT id') !== false) {
            $ids = [];

            foreach ($GLOBALS['aa_test_services'] as $row) {
                if ((int) ($row['active'] ?? 0) === 1 && (int) ($row['is_hidden'] ?? 0) === 0) {
                    $ids[] = (string) ($row['id'] ?? 0);
                }
            }

            return $ids;
        }

        return [];
    }

    public function get_row($query, $output = OBJECT) {
        $this->last_error = '';

        if (stripos($query, 'aa_clientes') !== false && stripos($query, 'telefono') !== false) {
            if (preg_match("/telefono = '?([^'\\s]+)'?/", $query, $matches) !== 1) {
                return null;
            }

            foreach ($GLOBALS['aa_test_clients'] as $row) {
                if (($row['telefono'] ?? '') === $matches[1]) {
                    return $output === ARRAY_A ? $row : (object) $row;
                }
            }

            return null;
        }

        if (preg_match("/name = '([^']+)'/", $query, $matches) === 1
            || preg_match('/name = ([^\\s]+)/', $query, $matches) === 1
        ) {
            $name = $matches[1];
            $bucket = null;

            if (stripos($query, 'aa_services') !== false) {
                $bucket = &$GLOBALS['aa_test_services'];
            } elseif (stripos($query, 'aa_staff') !== false) {
                $bucket = &$GLOBALS['aa_test_staff'];
            } elseif (stripos($query, 'aa_service_areas') !== false) {
                $bucket = &$GLOBALS['aa_test_areas'];
            }

            if ($bucket !== null) {
                foreach ($bucket as $row) {
                    if (($row['name'] ?? '') === $name
                        && (int) ($row['active'] ?? 0) === 1
                        && (int) ($row['is_hidden'] ?? 0) === 0
                    ) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
            }
        }

        return null;
    }

    public function insert($table, $data, $format = null) {
        $key = $this->table_key($table);

        if ($key === 'staff' && $GLOBALS['aa_test_force_staff_create_error']) {
            $this->last_error = 'forced staff insert error';

            return false;
        }

        $GLOBALS['aa_test_wpdb_insert_id']++;
        $row = $data;
        $row['id'] = $GLOBALS['aa_test_wpdb_insert_id'];

        if ($key === 'services' || $key === 'staff' || $key === 'areas') {
            $row['active'] = (int) ($row['active'] ?? 1);
            $row['is_hidden'] = (int) ($row['is_hidden'] ?? 0);
        }

        $this->bucket($key)[] = $row;
        $this->insert_id = $GLOBALS['aa_test_wpdb_insert_id'];
        $this->last_error = '';

        return 1;
    }
}

$GLOBALS['wpdb'] = new AA_Test_WPDB_Mock();

require_once $root . '/includes/domain/setup/class-aa-initial-setup-seed-definition.php';
require_once $root . '/includes/domain/setup/class-aa-initial-seed-eligibility-policy.php';
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/domain/setup/class-aa-initial-setup-seed-owner-email-resolver.php';
require_once $root . '/includes/repositories/ClientsRepository.php';
require_once $root . '/includes/repositories/AssignmentsRepository.php';
require_once $root . '/includes/application/assignments/AutoAssignStaffServicesUseCase.php';
require_once $root . '/includes/application/setup/SeedInitialSetupUseCase.php';
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
    $GLOBALS['aa_test_wpdb_insert_id'] = 0;
    $GLOBALS['aa_test_seed_calls'] = 0;
    $GLOBALS['aa_test_clients'] = [];
    $GLOBALS['aa_test_services'] = [];
    $GLOBALS['aa_test_staff'] = [];
    $GLOBALS['aa_test_areas'] = [];
    $GLOBALS['aa_test_staff_services'] = [];
    $GLOBALS['aa_test_force_staff_create_error'] = false;
    AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(null);
    AA_Initial_Seed_Eligibility_Lifecycle::set_facts_collector_for_tests(null);
}

function seed_set_default_facts_collector(): void {
    AA_Initial_Seed_Eligibility_Lifecycle::set_facts_collector_for_tests(static function (): array {
        $initialized_at = get_option(AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT, '');

        return [
            'has_installation_initialized_at' => is_string($initialized_at) && trim($initialized_at) !== '',
            'registered_client_count' => count($GLOBALS['aa_test_clients']),
            'active_service_count' => AssignmentsRepository::count_active_services(),
            'active_staff_count' => AssignmentsRepository::count_active_staff(),
            'active_area_count' => AssignmentsRepository::count_active_service_areas(),
            'created_reservation_count' => 0,
        ];
    });
}

function seed_run_eligibility_and_seed(): void {
    seed_set_default_facts_collector();
    AA_Initial_Seed_Eligibility_Lifecycle::maybe_evaluate();
    AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
}

function seed_find_by_name(array $rows, string $name): ?array {
    foreach ($rows as $row) {
        if (($row['name'] ?? '') === $name) {
            return $row;
        }
    }

    return null;
}

// --- AC1: Agenda nueva eligible sin seed version ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['admin_email'] = 'owner@agenda.test';
seed_run_eligibility_and_seed();

$client = $GLOBALS['aa_test_clients'][0] ?? null;
$service = seed_find_by_name($GLOBALS['aa_test_services'], AA_Initial_Setup_Seed_Definition::SERVICE_NAME);
$staff = seed_find_by_name($GLOBALS['aa_test_staff'], AA_Initial_Setup_Seed_Definition::STAFF_NAME);
$area = seed_find_by_name($GLOBALS['aa_test_areas'], AA_Initial_Setup_Seed_Definition::AREA_NAME);

ac_assert('AC1 eligible', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'eligible');
ac_assert('AC1 creates client', $client !== null);
ac_assert('AC1 client phone canonical', ($client['telefono'] ?? '') === AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL);
ac_assert('AC1 creates service', $service !== null);
ac_assert('AC1 creates staff', $staff !== null);
ac_assert('AC1 creates area', $area !== null);
ac_assert('AC1 staff-service link exists', count($GLOBALS['aa_test_staff_services']) > 0);
ac_assert(
    'AC1 marks seed version 2',
    ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === '2'
);

$onboarding_v2 = (new AA_Onboarding_Activation_Policy())->evaluate([
    'registered_client_count' => count($GLOBALS['aa_test_clients']),
    'active_service_count' => AssignmentsRepository::count_active_services(),
    'active_staff_count' => AssignmentsRepository::count_active_staff(),
    'active_staff_with_active_service_count' => AssignmentsRepository::count_active_staff_with_active_services(),
    'active_area_count' => AssignmentsRepository::count_active_service_areas(),
    'created_reservation_count' => 0,
]);
ac_assert('AC1 onboarding setup_complete', ($onboarding_v2['setup_complete'] ?? false) === true);
ac_assert('AC1 onboarding next first_appointment', ($onboarding_v2['next_step'] ?? null) === 'first_appointment');

// --- AC2: Agenda MC1 existente con seed_version = 1 ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] = 'eligible';
$GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] = AA_Initial_Setup_Seed_Definition::LEGACY_SEED_VERSION;
$GLOBALS['aa_test_clients'][] = [
    'id' => 1,
    'nombre' => AA_Initial_Setup_Seed_Definition::CLIENT_NAME,
    'telefono' => AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL,
    'correo' => 'owner@agenda.test',
];
AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_seed_calls']++;

    return ['status' => 'completed'];
});
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('AC2 MC1 version skips seed executor', (int) $GLOBALS['aa_test_seed_calls'] === 0);
ac_assert('AC2 does not create service', count($GLOBALS['aa_test_services']) === 0);
ac_assert('AC2 does not create staff', count($GLOBALS['aa_test_staff']) === 0);
ac_assert('AC2 does not create area', count($GLOBALS['aa_test_areas']) === 0);
ac_assert('AC2 keeps legacy seed version', ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === '1');
ac_assert('AC2 keeps single client', count($GLOBALS['aa_test_clients']) === 1);

// --- AC3: Agenda ineligible pre-MC1 ---

seed_reset_state();
seed_run_eligibility_and_seed();
ac_assert('AC3 ineligible', ($GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] ?? '') === 'ineligible');
ac_assert('AC3 creates nothing', count($GLOBALS['aa_test_clients']) === 0 && count($GLOBALS['aa_test_services']) === 0);
ac_assert(
    'AC3 does not mark seed version 2',
    !array_key_exists(AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION, $GLOBALS['aa_test_options'])
        || ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') !== '2'
);

// --- AC4: Reactivación / version = 2 ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] = 'eligible';
$GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] = AA_Initial_Setup_Seed_Definition::SEED_VERSION;
$GLOBALS['aa_test_clients'][] = ['id' => 1, 'nombre' => AA_Initial_Setup_Seed_Definition::CLIENT_NAME, 'telefono' => AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL, 'correo' => ''];
$GLOBALS['aa_test_services'][] = ['id' => 2, 'name' => AA_Initial_Setup_Seed_Definition::SERVICE_NAME, 'active' => 1, 'is_hidden' => 0];
AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_seed_calls']++;

    return ['status' => 'completed'];
});
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('AC4 reactivation skips executor', (int) $GLOBALS['aa_test_seed_calls'] === 0);
ac_assert('AC4 entity counts unchanged', count($GLOBALS['aa_test_clients']) === 1 && count($GLOBALS['aa_test_services']) === 1);

// --- AC5: Fallo parcial / retry ---

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options'][AA_Initial_Seed_Eligibility_Lifecycle::OPTION_ELIGIBILITY] = 'eligible';
$GLOBALS['aa_test_force_staff_create_error'] = true;
seed_set_default_facts_collector();
AA_Initial_Seed_Eligibility_Lifecycle::maybe_evaluate();
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('AC5 partial failure does not mark version 2', !isset($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION]));
ac_assert('AC5 partial creates client and service', count($GLOBALS['aa_test_clients']) === 1 && count($GLOBALS['aa_test_services']) === 1);
ac_assert('AC5 partial missing staff', count($GLOBALS['aa_test_staff']) === 0);

$GLOBALS['aa_test_force_staff_create_error'] = false;
AA_Initial_Setup_Seed_Lifecycle::maybe_seed();
ac_assert('AC5 retry completes staff and area', count($GLOBALS['aa_test_staff']) === 1 && count($GLOBALS['aa_test_areas']) === 1);
ac_assert(
    'AC5 retry marks version 2',
    ($GLOBALS['aa_test_options'][AA_Initial_Setup_Seed_Lifecycle::OPTION_SEED_VERSION] ?? '') === '2'
);
ac_assert('AC5 retry does not duplicate client', count($GLOBALS['aa_test_clients']) === 1);
ac_assert('AC5 retry does not duplicate service', count($GLOBALS['aa_test_services']) === 1);

// --- Email resolver ---

seed_reset_state();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-07-02 10:00:00';
$GLOBALS['aa_test_options']['deoia_owner_email'] = 'owner@agenda.test';
ac_assert(
    'Email resolver uses deoia_owner_email when provisioned',
    AA_Initial_Setup_Seed_Owner_Email_Resolver::resolve() === 'owner@agenda.test'
);

// --- Wiring ---

$bootstrap_src = file_get_contents($root . '/wp-agenda-automatizada.php');
ac_assert('Bootstrap registers initial setup seed lifecycle', strpos($bootstrap_src, 'AA_Initial_Setup_Seed_Lifecycle::register') !== false);

seed_reset_state();
$GLOBALS['aa_test_options'][AA_Schema::OPTION_INSTALLATION_INITIALIZED_AT] = '2026-07-02 10:00:00';
AA_Initial_Setup_Seed_Lifecycle::set_seed_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_seed_calls']++;

    return ['status' => 'completed'];
});
$GLOBALS['aa_test_doing_ajax'] = true;
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

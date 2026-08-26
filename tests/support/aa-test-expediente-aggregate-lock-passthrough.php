<?php
/**
 * Stub de named lock siempre exitoso para AC de writers (Ciclo A).
 *
 * Uso: require + AA_Expediente_Aggregate_Lock::set_default_for_tests(...)
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_message() {
            return $this->message;
        }
        public function get_error_code() {
            return $this->code;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}

if (!class_exists('AA_Test_Passthrough_Expediente_Aggregate_Lock')) {
    class AA_Test_Passthrough_Expediente_Aggregate_Lock extends AA_Expediente_Aggregate_Lock {
        /** @var list<array{scope_kind:string,scope_id:int,timeout_seconds:int}> */
        public $acquire_calls = [];

        /** @var int */
        public $release_calls = 0;

        /** @var WP_Error|null */
        public $next_acquire = null;

        /**
         * Secuencia opcional por acquire (P3: aggregate luego quota).
         * Cada entrada: null = éxito, WP_Error = fallo.
         *
         * @var list<WP_Error|null>|null
         */
        public $acquire_sequence = null;

        /** @var int */
        public $acquire_sequence_index = 0;

        /** @var list<AA_Expediente_Aggregate_Lock_Lease> */
        public $release_order = [];

        /** @var WP_Error|true|null */
        public $next_assert = null;

        public function acquire(string $scope_kind, int $scope_id, int $timeout_seconds = self::DEFAULT_TIMEOUT_SECONDS) {
            $this->acquire_calls[] = compact('scope_kind', 'scope_id', 'timeout_seconds');
            if (is_array($this->acquire_sequence)) {
                $idx = $this->acquire_sequence_index++;
                $forced = $this->acquire_sequence[$idx] ?? null;
                if ($forced instanceof WP_Error) {
                    return $forced;
                }
            } elseif ($this->next_acquire instanceof WP_Error) {
                return $this->next_acquire;
            }

            return new AA_Expediente_Aggregate_Lock_Lease('test-passthrough-key', 1, $scope_kind, $scope_id);
        }

        public function assert_held($lease) {
            if ($this->next_assert instanceof WP_Error) {
                return $this->next_assert;
            }

            return true;
        }

        public function release($lease): bool {
            $this->release_calls++;
            if ($lease instanceof AA_Expediente_Aggregate_Lock_Lease) {
                $this->release_order[] = $lease;
            }
            return true;
        }
    }
}

/**
 * Instala el passthrough como default de create_default().
 */
function aa_test_install_passthrough_expediente_lock(): AA_Test_Passthrough_Expediente_Aggregate_Lock {
    $lock = new AA_Test_Passthrough_Expediente_Aggregate_Lock();
    AA_Expediente_Aggregate_Lock::set_default_for_tests($lock);
    return $lock;
}

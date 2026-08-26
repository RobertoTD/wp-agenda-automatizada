<?php
/**
 * Named lock MySQL para coordinar mutaciones de un agregado de expediente.
 *
 * Scope server-side únicamente:
 * - client:{client_id} — expediente relacionado con cliente
 * - expediente:{expediente_id} — expediente general
 * - storage_quota:{1} — cuota global del blog (P3; tras aggregate)
 *
 * No expone la key. No acepta tenant/blog/prefix desde HTTP.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

/**
 * Lease opaco de un named lock adquirido.
 */
final class AA_Expediente_Aggregate_Lock_Lease {

    /** @var string */
    private $key;

    /** @var int */
    private $connection_id;

    /** @var string */
    private $scope_kind;

    /** @var int */
    private $scope_id;

    /**
     * @internal Solo construido por AA_Expediente_Aggregate_Lock.
     */
    public function __construct(string $key, int $connection_id, string $scope_kind, int $scope_id) {
        $this->key = $key;
        $this->connection_id = $connection_id;
        $this->scope_kind = $scope_kind;
        $this->scope_id = $scope_id;
    }

    /** @internal */
    public function key_for_lock(): string {
        return $this->key;
    }

    /** @internal */
    public function connection_id_for_lock(): int {
        return $this->connection_id;
    }

    public function scope_kind(): string {
        return $this->scope_kind;
    }

    public function scope_id(): int {
        return $this->scope_id;
    }
}

class AA_Expediente_Aggregate_Lock {

    public const SCOPE_CLIENT = 'client';
    public const SCOPE_EXPEDIENTE = 'expediente';
    /** Cuota de Storage global al blog (P3). scope_id fijo = 1. */
    public const SCOPE_STORAGE_QUOTA = 'storage_quota';
    public const STORAGE_QUOTA_SCOPE_ID = 1;

    public const DEFAULT_TIMEOUT_SECONDS = 1;
    public const MIN_TIMEOUT_SECONDS = 0;
    public const MAX_TIMEOUT_SECONDS = 5;

    public const ERROR_RESOURCE_BUSY = 'resource_busy';
    public const ERROR_COORDINATION_FAILED = 'coordination_failed';
    public const ERROR_COORDINATION_LOST = 'coordination_lost';
    public const ERROR_INVALID_SCOPE = 'invalid_lock_scope';

    /** @var self|null Override solo para acceptance tests. */
    private static $default_for_tests = null;

    /** @var callable(string, array<int,mixed>=):mixed|null */
    private $query;

    /** @var callable():mixed|null */
    private $connection_id_fn;

    /** @var callable(string):void|null */
    private $error_log;

    /**
     * @internal Acceptance tests only.
     */
    public static function set_default_for_tests(?self $lock): void {
        self::$default_for_tests = $lock;
    }

    public static function create_default(): self {
        return self::$default_for_tests instanceof self
            ? self::$default_for_tests
            : new self();
    }

    /**
     * @param callable(string, array<int,mixed>=):mixed|null $query
     * @param callable():mixed|null $connection_id_fn
     * @param callable(string):void|null $error_log
     */
    public function __construct($query = null, $connection_id_fn = null, $error_log = null) {
        $this->query = $query;
        $this->connection_id_fn = $connection_id_fn;
        $this->error_log = $error_log;
    }

    /**
     * @return AA_Expediente_Aggregate_Lock_Lease|WP_Error
     */
    public function acquire(string $scope_kind, int $scope_id, int $timeout_seconds = self::DEFAULT_TIMEOUT_SECONDS) {
        if ($scope_kind === self::SCOPE_STORAGE_QUOTA) {
            if ($scope_id !== self::STORAGE_QUOTA_SCOPE_ID) {
                return new WP_Error(self::ERROR_INVALID_SCOPE, 'Identificador de ámbito no válido.');
            }
        } elseif ($scope_kind !== self::SCOPE_CLIENT && $scope_kind !== self::SCOPE_EXPEDIENTE) {
            return new WP_Error(self::ERROR_INVALID_SCOPE, 'Ámbito de coordinación no válido.');
        } elseif ($scope_id < 1) {
            return new WP_Error(self::ERROR_INVALID_SCOPE, 'Identificador de ámbito no válido.');
        }

        if (
            $timeout_seconds < self::MIN_TIMEOUT_SECONDS
            || $timeout_seconds > self::MAX_TIMEOUT_SECONDS
        ) {
            return new WP_Error(self::ERROR_INVALID_SCOPE, 'Timeout de coordinación no válido.');
        }

        $key = $this->build_key($scope_kind, $scope_id);
        if ($key === null) {
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        $got = $this->run_query(
            'SELECT GET_LOCK(%s, %d)',
            [$key, $timeout_seconds]
        );

        if ($this->has_last_error()) {
            $this->log_generic('acquire query error');
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        if ($got === null || $got === false) {
            $this->log_generic('acquire null result');
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        if (is_string($got) && ctype_digit($got)) {
            $got = (int) $got;
        }

        if (!is_int($got) && !(is_numeric($got) && (string) (int) $got === (string) $got)) {
            $this->log_generic('acquire malformed result');
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        $got = (int) $got;

        if ($got === 0) {
            return new WP_Error(self::ERROR_RESOURCE_BUSY, 'El expediente está ocupado. Inténtalo de nuevo.');
        }

        if ($got !== 1) {
            $this->log_generic('acquire unexpected result');
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        $connection_id = $this->read_connection_id();
        if ($connection_id === null) {
            $this->release_key_quietly($key);
            return new WP_Error(self::ERROR_COORDINATION_FAILED, 'No se pudo coordinar la operación.');
        }

        return new AA_Expediente_Aggregate_Lock_Lease($key, $connection_id, $scope_kind, $scope_id);
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease|mixed $lease
     * @return true|WP_Error
     */
    public function assert_held($lease) {
        if (!($lease instanceof AA_Expediente_Aggregate_Lock_Lease)) {
            return new WP_Error(self::ERROR_COORDINATION_LOST, 'Se perdió la coordinación de la operación.');
        }

        $owner = $this->run_query(
            'SELECT IS_USED_LOCK(%s)',
            [$lease->key_for_lock()]
        );

        if ($this->has_last_error()) {
            $this->log_generic('assert_held query error');
            return new WP_Error(self::ERROR_COORDINATION_LOST, 'Se perdió la coordinación de la operación.');
        }

        if ($owner === null || $owner === false || $owner === '') {
            return new WP_Error(self::ERROR_COORDINATION_LOST, 'Se perdió la coordinación de la operación.');
        }

        if (is_string($owner) && ctype_digit($owner)) {
            $owner = (int) $owner;
        }

        if (!is_int($owner) && !(is_numeric($owner) && (string) (int) $owner === (string) $owner)) {
            return new WP_Error(self::ERROR_COORDINATION_LOST, 'Se perdió la coordinación de la operación.');
        }

        if ((int) $owner !== $lease->connection_id_for_lock()) {
            return new WP_Error(self::ERROR_COORDINATION_LOST, 'Se perdió la coordinación de la operación.');
        }

        return true;
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease|mixed $lease
     */
    public function release($lease): bool {
        if (!($lease instanceof AA_Expediente_Aggregate_Lock_Lease)) {
            $this->log_generic('release invalid lease');
            return false;
        }

        $released = $this->run_query(
            'SELECT RELEASE_LOCK(%s)',
            [$lease->key_for_lock()]
        );

        if ($this->has_last_error()) {
            $this->log_generic('release query error');
            return false;
        }

        if ($released === null || $released === false) {
            $this->log_generic('release null result');
            return false;
        }

        if (is_string($released) && ctype_digit($released)) {
            $released = (int) $released;
        }

        if ((int) $released === 1) {
            return true;
        }

        $this->log_generic('release not owned or already free');
        return false;
    }

    /**
     * Construye la key determinista (solo para tests; no registrar en logs).
     *
     * @return string|null
     */
    public function build_key_for_tests(string $scope_kind, int $scope_id): ?string {
        return $this->build_key($scope_kind, $scope_id);
    }

    /**
     * @return string|null
     */
    private function build_key(string $scope_kind, int $scope_id): ?string {
        global $wpdb;

        $base_prefix = '';
        $prefix = '';
        if (isset($wpdb) && is_object($wpdb)) {
            if (isset($wpdb->base_prefix)) {
                $base_prefix = (string) $wpdb->base_prefix;
            }
            if (isset($wpdb->prefix)) {
                $prefix = (string) $wpdb->prefix;
            }
        }

        $network_id = function_exists('get_current_network_id')
            ? (string) get_current_network_id()
            : '0';
        $blog_id = function_exists('get_current_blog_id')
            ? (string) get_current_blog_id()
            : '0';

        $namespace = implode("\0", [
            defined('DB_HOST') ? (string) DB_HOST : '',
            defined('DB_NAME') ? (string) DB_NAME : '',
            $base_prefix,
            $network_id,
            $prefix,
            $blog_id,
            $scope_kind,
            (string) $scope_id,
        ]);

        $digest = hash('sha256', $namespace);
        if (!is_string($digest) || strlen($digest) < 56) {
            return null;
        }

        $key = 'aaexp:' . substr($digest, 0, 56);
        if (strlen($key) > 62) {
            return null;
        }

        return $key;
    }

    /**
     * @return int|null
     */
    private function read_connection_id(): ?int {
        if (is_callable($this->connection_id_fn)) {
            $raw = ($this->connection_id_fn)();
        } else {
            $raw = $this->run_query('SELECT CONNECTION_ID()', []);
        }

        if ($this->has_last_error() && !is_callable($this->connection_id_fn)) {
            $this->log_generic('connection_id query error');
            return null;
        }

        if ($raw === null || $raw === false || $raw === '') {
            $this->log_generic('connection_id empty');
            return null;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            $raw = (int) $raw;
        }

        if (!is_int($raw) && !(is_numeric($raw) && (string) (int) $raw === (string) $raw)) {
            $this->log_generic('connection_id malformed');
            return null;
        }

        $id = (int) $raw;
        if ($id < 1) {
            $this->log_generic('connection_id invalid');
            return null;
        }

        return $id;
    }

    /**
     * @param list<mixed> $args
     * @return mixed
     */
    private function run_query(string $sql, array $args = []) {
        if (is_callable($this->query)) {
            return ($this->query)($sql, $args);
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var')) {
            return null;
        }

        if ($args === []) {
            return $wpdb->get_var($sql);
        }

        if (!method_exists($wpdb, 'prepare')) {
            return null;
        }

        $prepared = $wpdb->prepare($sql, ...$args);
        if (!is_string($prepared) || $prepared === '') {
            return null;
        }

        return $wpdb->get_var($prepared);
    }

    private function has_last_error(): bool {
        if (is_callable($this->query)) {
            return false;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $err = isset($wpdb->last_error) ? (string) $wpdb->last_error : '';

        return $err !== '';
    }

    private function release_key_quietly(string $key): void {
        $this->run_query('SELECT RELEASE_LOCK(%s)', [$key]);
    }

    private function log_generic(string $reason): void {
        $message = '[AA_Expediente_Aggregate_Lock] coordination issue';
        if (is_callable($this->error_log)) {
            ($this->error_log)($message);
            return;
        }
        if (function_exists('error_log')) {
            error_log($message);
        }
        unset($reason);
    }
}

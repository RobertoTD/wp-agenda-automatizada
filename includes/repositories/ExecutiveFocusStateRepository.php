<?php
/**
 * Executive Focus State Repository — foco manual y streak en user_meta (MC5).
 *
 * SQL/WP puro: sin reglas de negocio.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/domain/executive/class-aa-executive-focus-state-policy.php';

final class ExecutiveFocusStateRepository {

    public const META_KEY = 'aa_executive_focus_state_v1';

    /** @var callable|null */
    private static $storage_override = null;

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $override Debe aceptar (string $operation, int $user_id, mixed $payload = null).
     */
    public static function set_storage_override_for_tests(?callable $override): void {
        self::$storage_override = $override;
    }

    /**
     * @param int $user_id
     * @return array<string,mixed>
     */
    public static function find_for_user(int $user_id): array {
        if ($user_id < 1) {
            return AA_Executive_Focus_State_Policy::empty_state();
        }

        $raw = self::read_raw($user_id);

        return AA_Executive_Focus_State_Policy::sanitize($raw);
    }

    /**
     * @param int                 $user_id
     * @param array<string,mixed> $state
     */
    public static function save_for_user(int $user_id, array $state): bool {
        if ($user_id < 1) {
            return false;
        }

        $sanitized = AA_Executive_Focus_State_Policy::sanitize($state);

        return self::write_raw($user_id, $sanitized);
    }

    /**
     * @param int $user_id
     */
    public static function clear_for_user(int $user_id): bool {
        if ($user_id < 1) {
            return false;
        }

        return self::delete_raw($user_id);
    }

    /**
     * @param int $user_id
     * @return mixed
     */
    private static function read_raw(int $user_id) {
        if (self::$storage_override !== null) {
            return call_user_func(self::$storage_override, 'read', $user_id);
        }

        if (!function_exists('get_user_meta')) {
            return null;
        }

        $raw = get_user_meta($user_id, self::META_KEY, true);

        if ($raw === '' || $raw === false) {
            return null;
        }

        return $raw;
    }

    /**
     * @param int                 $user_id
     * @param array<string,mixed> $state
     */
    private static function write_raw(int $user_id, array $state): bool {
        if (self::$storage_override !== null) {
            $result = call_user_func(self::$storage_override, 'write', $user_id, $state);

            return $result !== false;
        }

        if (!function_exists('update_user_meta')) {
            return false;
        }

        $encoded = wp_json_encode($state);

        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        return update_user_meta($user_id, self::META_KEY, $encoded) !== false;
    }

    /**
     * @param int $user_id
     */
    private static function delete_raw(int $user_id): bool {
        if (self::$storage_override !== null) {
            $result = call_user_func(self::$storage_override, 'delete', $user_id);

            return $result !== false;
        }

        if (!function_exists('delete_user_meta')) {
            return false;
        }

        return delete_user_meta($user_id, self::META_KEY) !== false;
    }
}

<?php
/**
 * Tutorial State Repository — persistencia site-scoped vía Options API.
 *
 * En single-site usa wp_options; en Multisite usa la tabla de opciones del sitio actual.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/domain/tutorials/class-aa-tutorial-state-policy.php';

final class TutorialStateRepository {

    public const OPTION_KEY = 'aa_tutorial_state_v1';

    /** @var callable|null Override for acceptance tests only. */
    private static $storage_override = null;

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $override Debe aceptar (string $operation, int $blog_id, mixed $payload = null).
     */
    public static function set_storage_override_for_tests(?callable $override): void {
        self::$storage_override = $override;
    }

    /**
     * @return array<string,mixed>
     */
    public static function find(): array {
        $raw = self::read_raw();

        if ($raw === false) {
            return AA_Tutorial_State_Policy::empty_state();
        }

        return AA_Tutorial_State_Policy::sanitize($raw);
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function save(array $state): bool {
        $sanitized = AA_Tutorial_State_Policy::sanitize($state);

        return self::write_raw($sanitized);
    }

    /**
     * @return mixed
     */
    private static function read_raw() {
        if (self::$storage_override !== null) {
            return call_user_func(self::$storage_override, 'read', self::current_blog_id());
        }

        if (!function_exists('get_option')) {
            return false;
        }

        return get_option(self::OPTION_KEY, false);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function write_raw(array $state): bool {
        if (self::$storage_override !== null) {
            $result = call_user_func(self::$storage_override, 'write', self::current_blog_id(), $state);

            return $result !== false;
        }

        if (!function_exists('update_option')) {
            return false;
        }

        $updated = update_option(self::OPTION_KEY, $state, false);

        if ($updated) {
            return true;
        }

        if (!function_exists('get_option')) {
            return false;
        }

        $stored = get_option(self::OPTION_KEY, false);

        if (!is_array($stored)) {
            return false;
        }

        $sanitized = AA_Tutorial_State_Policy::sanitize($stored);

        return $sanitized === AA_Tutorial_State_Policy::sanitize($state);
    }

    /**
     * @return int
     */
    private static function current_blog_id(): int {
        if (function_exists('get_current_blog_id')) {
            return max(1, (int) get_current_blog_id());
        }

        return 1;
    }
}

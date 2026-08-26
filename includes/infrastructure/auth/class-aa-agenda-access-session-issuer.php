<?php
/**
 * Issues a native WP auth cookie with a fixed 30-day remember TTL for agenda magic login only.
 *
 * The auth_cookie_expiration filter is installed only for this call and always removed.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Access_Session_Issuer {

    /** 30 days in seconds (fixed remember TTL for magic login). */
    public const REMEMBER_TTL_SECONDS = 2592000;

    /** @var bool */
    private static $filter_active = false;

    /**
     * @return bool True when the filter is currently registered (tests).
     */
    public static function is_expiration_filter_active(): bool {
        return self::$filter_active;
    }

    /**
     * Establish WP session for $user_id with remember=true and 30-day expiry.
     */
    public function establish(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return;
        }

        $filter = static function ($length, $uid, $remember) {
            unset($uid);
            if (!self::$filter_active) {
                return $length;
            }
            if (!$remember) {
                return $length;
            }
            return self::REMEMBER_TTL_SECONDS;
        };

        self::$filter_active = true;
        add_filter('auth_cookie_expiration', $filter, 99, 3);

        try {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', $user->user_login, $user);
        } finally {
            remove_filter('auth_cookie_expiration', $filter, 99);
            self::$filter_active = false;
        }
    }
}

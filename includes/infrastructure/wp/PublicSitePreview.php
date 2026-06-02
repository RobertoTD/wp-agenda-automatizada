<?php
/**
 * Public site preview mode — visitor-style frontend view for site admins.
 *
 * When the home URL is opened with ?deoia_public_preview=1, logged-in users
 * with manage_options see the public frontend without the WordPress admin bar.
 * Does not bypass maintenance, change session, or affect normal frontend URLs.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Public_Site_Preview')) {
    final class AA_Public_Site_Preview {

        public const QUERY_KEY = 'deoia_public_preview';

        public const QUERY_VALUE = '1';

        public static function register(): void {
            add_filter('show_admin_bar', [__CLASS__, 'filter_show_admin_bar'], 20);
        }

        /**
         * Home URL for previewing the public site as a visitor would see it.
         */
        public static function public_url(): string {
            return add_query_arg(
                self::QUERY_KEY,
                self::QUERY_VALUE,
                home_url('/')
            );
        }

        /**
         * Whether the current frontend request is a public preview visit.
         */
        public static function is_preview_request(): bool {
            if (function_exists('is_admin') && is_admin()) {
                return false;
            }

            if (!isset($_GET[self::QUERY_KEY])) {
                return false;
            }

            return (string) wp_unslash($_GET[self::QUERY_KEY]) === self::QUERY_VALUE;
        }

        /**
         * @param bool $show Whether WordPress would show the admin bar.
         */
        public static function filter_show_admin_bar(bool $show): bool {
            if (!self::is_preview_request()) {
                return $show;
            }

            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                return $show;
            }

            return false;
        }
    }
}

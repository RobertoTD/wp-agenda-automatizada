<?php
/**
 * Public frontend maintenance guard for provisioned DEOIA sites.
 *
 * Intercepts only public frontend requests when the current site is a
 * provisioned DEOIA installation and the local public site status is
 * explicitly set to maintenance.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Public_Site_Maintenance_Guard')) {
    final class AA_Public_Site_Maintenance_Guard {

        private const RETRY_AFTER_SECONDS = 3600;

        /**
         * Register the guard early in frontend template routing.
         */
        public static function register(): void {
            add_action('template_redirect', [__CLASS__, 'handle_template_redirect'], 1);
        }

        public static function handle_template_redirect(): void {
            if (!self::should_intercept_current_request()) {
                return;
            }

            self::render_maintenance_response();
        }

        /**
         * Whether the current request should be replaced with the maintenance page.
         */
        public static function should_intercept_current_request(): bool {
            if (!class_exists('AA_Installation_Provisioning_Detector')
                || !AA_Installation_Provisioning_Detector::is_provisioned()
            ) {
                return false;
            }

            if (!class_exists('AA_Public_Site_Status')
                || AA_Public_Site_Status::current() !== AA_Public_Site_Status::STATUS_MAINTENANCE
            ) {
                return false;
            }

            return !self::is_excluded_request();
        }

        /**
         * Contexts and paths that must never be intercepted.
         */
        public static function is_excluded_request(): bool {
            if (function_exists('is_admin') && is_admin()) {
                return true;
            }

            if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
                return true;
            }

            if ((defined('REST_REQUEST') && REST_REQUEST)
                || (defined('WP_CLI') && WP_CLI)
                || (defined('DOING_CRON') && DOING_CRON)
            ) {
                return true;
            }

            return self::is_excluded_path(self::current_request_path());
        }

        /**
         * @internal Public for focused AC tests.
         */
        public static function is_excluded_path(string $path): bool {
            $path = self::normalize_path($path);

            $exact_paths = [
                '/favicon.ico',
                '/robots.txt',
                '/wp-cron.php',
                '/wp-login.php',
                '/xmlrpc.php',
            ];

            if (in_array($path, $exact_paths, true)) {
                return true;
            }

            $prefixes = [
                '/agenda-app/',
                '/citas-virtuales/',
                '/wp-admin/',
                '/wp-content/',
                '/wp-includes/',
                '/wp-json/',
            ];

            foreach ($prefixes as $prefix) {
                if (strpos($path, $prefix) === 0) {
                    return true;
                }
            }

            return false;
        }

        private static function current_request_path(): string {
            $request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
                ? $_SERVER['REQUEST_URI']
                : '/';

            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = '/';
            }

            return self::strip_home_path($path);
        }

        private static function strip_home_path(string $path): string {
            if (!function_exists('home_url')) {
                return self::normalize_path($path);
            }

            $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
            if (!is_string($home_path) || $home_path === '' || $home_path === '/') {
                return self::normalize_path($path);
            }

            $home_path = self::normalize_path($home_path);

            if (strpos($path, $home_path) !== 0) {
                return self::normalize_path($path);
            }

            $relative = substr($path, strlen(rtrim($home_path, '/')));

            return self::normalize_path($relative === false ? '/' : $relative);
        }

        private static function normalize_path(string $path): string {
            $path = rawurldecode($path);

            if ($path === '') {
                return '/';
            }

            if ($path[0] !== '/') {
                $path = '/' . $path;
            }

            if ($path !== '/') {
                $path = '/' . trim($path, '/') . '/';

                $filename_paths = [
                    '/favicon.ico/',
                    '/robots.txt/',
                    '/wp-cron.php/',
                    '/wp-login.php/',
                    '/xmlrpc.php/',
                ];

                if (in_array($path, $filename_paths, true)) {
                    return rtrim($path, '/');
                }
            }

            return $path;
        }

        private static function render_maintenance_response(): void {
            if (function_exists('status_header')) {
                status_header(503);
            }

            if (function_exists('nocache_headers')) {
                nocache_headers();
            }

            header('Retry-After: ' . self::RETRY_AFTER_SECONDS);
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
            header('Content-Type: text/html; charset=utf-8', true);

            $view = AA_PLUGIN_PATH . 'views/public-site-maintenance.php';
            if (is_readable($view)) {
                require $view;
            } else {
                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Agenda en preparación</title></head><body><h1>Agenda en preparación</h1><p>Esta agenda estará disponible pronto.</p></body></html>';
            }

            exit;
        }
    }
}

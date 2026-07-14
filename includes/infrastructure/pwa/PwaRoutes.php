<?php
/**
 * PWA asset routes — manifest and service worker for the admin app shell.
 *
 * Served via admin-post.php so URLs stay under /wp-admin/ and work with
 * subdirectory / multisite installs via admin_url().
 */

defined('ABSPATH') or die('No direct access');

class AA_Pwa_Routes {

    private const MANIFEST_ACTION = 'aa_pwa_manifest';
    private const SW_ACTION       = 'aa_pwa_service_worker';

    public static function register(): void {
        add_action('admin_post_' . self::MANIFEST_ACTION, [__CLASS__, 'serve_manifest']);
        add_action('admin_post_nopriv_' . self::MANIFEST_ACTION, [__CLASS__, 'serve_manifest']);
        add_action('admin_post_' . self::SW_ACTION, [__CLASS__, 'serve_service_worker']);
        add_action('admin_post_nopriv_' . self::SW_ACTION, [__CLASS__, 'serve_service_worker']);
    }

    public static function manifest_url(): string {
        return admin_url('admin-post.php?action=' . self::MANIFEST_ACTION);
    }

    public static function service_worker_url(): string {
        return admin_url('admin-post.php?action=' . self::SW_ACTION);
    }

    /**
     * Path prefix for manifest scope and service worker registration (e.g. /wp-admin/).
     */
    public static function scope_path(): string {
        $path = wp_parse_url(admin_url('/'), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/wp-admin/';
        }
        return trailingslashit($path);
    }

    public static function serve_manifest(): void {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');

        $icons_base = AA_PLUGIN_URL . 'includes/admin/ui/pwa/icons/';
        $manifest   = [
            'name'             => 'DEOIA',
            'short_name'       => 'DEOIA',
            'start_url'        => admin_url('admin-post.php?action=aa_iframe_content&module=calendar'),
            'scope'            => admin_url('/'),
            'display'          => 'standalone',
            'theme_color'      => '#8b5cf6',
            'background_color' => '#f0f0f1',
            'icons'            => [
                [
                    'src'     => $icons_base . 'icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => $icons_base . 'icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ];

        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function serve_service_worker(): void {
        $sw_path = AA_PLUGIN_PATH . 'includes/admin/ui/pwa/sw.js';
        if (!is_readable($sw_path)) {
            status_header(404);
            exit;
        }

        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . self::scope_path());

        $api_base = self::resolve_push_api_base_for_sw();
        echo 'self.__AA_PUSH_API_BASE__ = ' . wp_json_encode($api_base, JSON_UNESCAPED_SLASHES) . ";\n";
        readfile($sw_path);
        exit;
    }

    /**
     * Absolute API origin/base for SW → oauth-backend calls (not a secret).
     * Empty string when missing or invalid — SW uses conservative fallback.
     */
    private static function resolve_push_api_base_for_sw(): string {
        if (!defined('AA_API_BASE_URL') || !is_string(AA_API_BASE_URL)) {
            return '';
        }

        $raw = trim(AA_API_BASE_URL);
        if ($raw === '') {
            return '';
        }

        $sanitized = esc_url_raw($raw);
        if (!is_string($sanitized) || $sanitized === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $sanitized)) {
            return '';
        }

        return untrailingslashit($sanitized);
    }
}

<?php
/**
 * Allowlisted post–magic-login destinations (same-site calendar shell).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Access_Redirect_Policy {

    public const MODULE_CALENDAR = 'calendar';

    private const ALLOWED_MODULES = [
        self::MODULE_CALENDAR,
    ];

    /**
     * Canonical success URL for a consume purpose (welcome | login_request → calendar).
     */
    public function success_url(string $purpose = ''): string {
        unset($purpose); // Reserved for future purpose-specific modules; both map to calendar in C2.
        return $this->calendar_url();
    }

    public function calendar_url(): string {
        return admin_url('admin-post.php?action=aa_iframe_content&module=' . self::MODULE_CALENDAR);
    }

    /**
     * Login URL (app skin) with generic error flag — never includes tokens.
     */
    public function error_login_url(): string {
        $target = $this->calendar_url();
        $login  = function_exists('aa_app_login_url')
            ? aa_app_login_url($target)
            : wp_login_url($target);

        return add_query_arg('aa_agenda_access_error', '1', $login);
    }

    /**
     * Default post-login destination for app login (agenda-app entry).
     */
    public function default_app_redirect(): string {
        return function_exists('home_url')
            ? home_url('/agenda-app/')
            : $this->calendar_url();
    }

    /**
     * Sanitize a candidate redirect_to for app login (no open redirects).
     */
    public function sanitize_login_redirect(?string $candidate): string {
        $fallback = $this->default_app_redirect();
        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate === '') {
            return $fallback;
        }

        if (function_exists('wp_validate_redirect')) {
            $validated = wp_validate_redirect($candidate, false);
            if (!is_string($validated) || $validated === '') {
                return $fallback;
            }
            $candidate = $validated;
        }

        if (function_exists('aa_redirect_to_is_app_context')
            && aa_redirect_to_is_app_context($candidate)
        ) {
            return $candidate;
        }

        if ($this->is_allowlisted_success_url($candidate)) {
            return $candidate;
        }

        return $fallback;
    }

    /**
     * Login URL (app skin) after link-request PRG — neutral flag, never tokens/PII.
     */
    public function link_sent_login_url(?string $redirect_to = null): string {
        $target = $this->sanitize_login_redirect($redirect_to);
        $login  = function_exists('aa_app_login_url')
            ? aa_app_login_url($target)
            : wp_login_url($target);

        return add_query_arg('aa_agenda_access_link_sent', '1', $login);
    }

    /**
     * Whether a candidate redirect is an allowlisted same-site agenda shell URL.
     */
    public function is_allowlisted_success_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $canonical = $this->calendar_url();
        $parsed    = wp_parse_url($url);
        $expected  = wp_parse_url($canonical);

        if (!is_array($parsed) || !is_array($expected)) {
            return false;
        }

        foreach (['scheme', 'host'] as $part) {
            $a = isset($parsed[$part]) ? strtolower((string) $parsed[$part]) : '';
            $b = isset($expected[$part]) ? strtolower((string) $expected[$part]) : '';
            if ($a !== $b) {
                return false;
            }
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        $exp_path = isset($expected['path']) ? (string) $expected['path'] : '';
        if ($path !== $exp_path) {
            return false;
        }

        $query = [];
        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }

        $action = isset($query['action']) ? sanitize_key((string) $query['action']) : '';
        $module = isset($query['module']) ? sanitize_key((string) $query['module']) : '';

        if ($action !== 'aa_iframe_content') {
            return false;
        }

        return in_array($module, self::ALLOWED_MODULES, true);
    }
}

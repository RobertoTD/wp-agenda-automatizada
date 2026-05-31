<?php
/**
 * DEOIA Citas app login context — detection and login URL helper (AL-1).
 *
 * Visual skin (CSS/branding) is reserved for AL-2. This module only:
 * - builds login URLs flagged for the app context
 * - detects when wp-login.php is serving an app-bound redirect
 * - exposes a temporary login_message marker for validation
 */

defined('ABSPATH') or die('No direct access');

class AA_App_Login_Skin {

    public const QUERY_FLAG = 'deoia_app_login';

    public static function register(): void {
        add_filter('login_message', [__CLASS__, 'filter_login_message']);
    }

    /**
     * Temporary AL-1 marker; replaced by branded skin in AL-2.
     */
    public static function filter_login_message(string $message): string {
        if (!aa_is_deoia_app_login_context()) {
            return $message;
        }

        $marker = '<p class="message">DEOIA Citas — inicio de sesión para la app.</p>';

        return $marker . $message;
    }
}

/**
 * Login URL for app-bound redirects, flagged for DEOIA app login context.
 *
 * @param string $redirect_to Post-login destination (agenda-app or admin-post app shell).
 */
function aa_app_login_url(string $redirect_to): string {
    return add_query_arg(
        AA_App_Login_Skin::QUERY_FLAG,
        '1',
        wp_login_url($redirect_to)
    );
}

/**
 * Whether redirect_to targets the DEOIA Citas app entry points.
 */
function aa_redirect_to_is_app_context(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    if (preg_match('~/agenda-app/?([?#]|$)~', $url) === 1) {
        return true;
    }

    if (strpos($url, 'action=aa_iframe_content') !== false) {
        return true;
    }

    return false;
}

/**
 * True when wp-login.php is serving a DEOIA Citas app login (explicit flag or redirect_to).
 */
function aa_is_deoia_app_login_context(): bool {
    if (isset($_GET[AA_App_Login_Skin::QUERY_FLAG])
        && (string) $_GET[AA_App_Login_Skin::QUERY_FLAG] === '1') {
        return true;
    }

    $redirect_to = '';
    if (isset($_REQUEST['redirect_to'])) {
        $redirect_to = wp_unslash((string) $_REQUEST['redirect_to']);
    }

    return aa_redirect_to_is_app_context($redirect_to);
}

<?php
/**
 * DEOIA Citas app login context — detection, URL helper, and visual skin (AL-1 + AL-2).
 *
 * Builds login URLs flagged for the app context, detects wp-login.php app-bound
 * requests, and applies branded styling only in that context.
 */

defined('ABSPATH') or die('No direct access');

class AA_App_Login_Skin {

    public const QUERY_FLAG = 'deoia_app_login';

    private const CSS_RELATIVE = 'includes/admin/ui/assets/css/deoia-app-login.css';

    public static function register(): void {
        add_filter('login_message', [__CLASS__, 'filter_login_message']);
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_login_styles']);
        add_filter('login_body_class', [__CLASS__, 'filter_login_body_class'], 10, 1);
        add_filter('login_headerurl', [__CLASS__, 'filter_login_headerurl']);
        add_filter('login_headertext', [__CLASS__, 'filter_login_headertext']);
        add_action('login_head', [__CLASS__, 'render_login_head_meta']);
    }

    public static function filter_login_message(string $message): string {
        if (!aa_is_deoia_app_login_context()) {
            return $message;
        }

        $marker = '<p class="message aa-deoia-login-message"><strong>Accede a DEOIA Citas</strong></p>';

        return $marker . $message;
    }

    public static function enqueue_login_styles(): void {
        if (!aa_is_deoia_app_login_context()) {
            return;
        }

        $path = AA_PLUGIN_PATH . self::CSS_RELATIVE;
        $url  = AA_PLUGIN_URL . self::CSS_RELATIVE;
        $ver  = is_readable($path) ? (string) filemtime($path) : AA_PLUGIN_VERSION;

        wp_enqueue_style('aa-deoia-app-login', $url, [], $ver);
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public static function filter_login_body_class(array $classes): array {
        if (aa_is_deoia_app_login_context()) {
            $classes[] = 'aa-deoia-app-login';
        }

        return $classes;
    }

    public static function filter_login_headerurl(string $url): string {
        if (!aa_is_deoia_app_login_context()) {
            return $url;
        }

        return home_url('/agenda-app/');
    }

    public static function filter_login_headertext(string $text): string {
        if (!aa_is_deoia_app_login_context()) {
            return $text;
        }

        return 'DEOIA Citas';
    }

    public static function render_login_head_meta(): void {
        if (!aa_is_deoia_app_login_context()) {
            return;
        }

        echo '<meta name="theme-color" content="#8b5cf6">' . "\n";
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

<?php
/**
 * Public admin-post handlers for agenda magic-link bridge, consume, and request (C2+C3B).
 *
 * Never logs tokens, secrets, emails, or sensitive backend bodies.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-token-format.php';
require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-redirect-policy.php';
require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-request-eligibility.php';
require_once dirname(__DIR__, 2) . '/application/auth/ConsumeAgendaAccessTokenUseCase.php';
require_once dirname(__DIR__, 2) . '/application/auth/RequestAgendaAccessLinkUseCase.php';

final class AA_Agenda_Access_Handlers {

    public const ACTION_BRIDGE = 'aa_agenda_access';
    public const ACTION_CONSUME = 'aa_agenda_access_consume';
    public const ACTION_REQUEST = 'aa_agenda_access_request';

    public const NONCE_ACTION = 'aa_agenda_access_request';
    public const NONCE_FIELD = 'aa_agenda_access_request_nonce';

    /** @var RequestAgendaAccessLinkUseCase|null Injected in tests. */
    private static $request_use_case = null;

    public static function register(): void {
        add_action('admin_post_nopriv_' . self::ACTION_BRIDGE, [__CLASS__, 'handle_bridge']);
        add_action('admin_post_' . self::ACTION_BRIDGE, [__CLASS__, 'handle_bridge']);
        add_action('admin_post_nopriv_' . self::ACTION_CONSUME, [__CLASS__, 'handle_consume']);
        add_action('admin_post_' . self::ACTION_CONSUME, [__CLASS__, 'handle_consume']);
        add_action('admin_post_nopriv_' . self::ACTION_REQUEST, [__CLASS__, 'handle_request']);
        add_action('admin_post_' . self::ACTION_REQUEST, [__CLASS__, 'handle_request']);
        add_filter('login_message', [__CLASS__, 'filter_login_error_message']);
        add_filter('login_message', [__CLASS__, 'filter_login_request_ui'], 11);
        add_action('login_footer', [__CLASS__, 'render_request_button_script']);
    }

    /**
     * @internal Tests only.
     */
    public static function set_request_use_case(?RequestAgendaAccessLinkUseCase $use_case): void {
        self::$request_use_case = $use_case;
    }

    /**
     * Generic error banner on wp-login when redirected after a failed magic link.
     */
    public static function filter_login_error_message(string $message): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
        if (!isset($_GET['aa_agenda_access_error']) || (string) wp_unslash($_GET['aa_agenda_access_error']) !== '1') {
            return $message;
        }

        $notice = '<p class="message aa-agenda-access-error">'
            . esc_html__('No pudimos iniciar sesión con este enlace. Usa tu usuario y contraseña.', 'wp-agenda-automatizada')
            . '</p>';

        return $notice . $message;
    }

    /**
     * C3B: neutral “link sent” notice + CTA (composed with AppLoginSkin / C2 messages).
     */
    public static function filter_login_request_ui(string $message): string {
        if (!function_exists('aa_is_deoia_app_login_context') || !aa_is_deoia_app_login_context()) {
            return $message;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
        if (isset($_GET['aa_agenda_access_link_sent'])
            && (string) wp_unslash($_GET['aa_agenda_access_link_sent']) === '1'
        ) {
            $notice = '<p class="message aa-agenda-access-link-sent">'
                . esc_html__(
                    'Si esta agenda admite acceso por enlace, recibirás un correo con las instrucciones.',
                    'wp-agenda-automatizada'
                )
                . '</p>';
            $message = $notice . $message;
        }

        if (self::should_render_request_cta()) {
            $message .= self::render_request_cta_html();
        }

        return $message;
    }

    /**
     * Minimal JS: disable CTA submit button after click (anti double-submit UX).
     */
    public static function render_request_button_script(): void {
        if (!function_exists('aa_is_deoia_app_login_context') || !aa_is_deoia_app_login_context()) {
            return;
        }

        echo "<script>\n";
        echo "(function(){var f=document.getElementById('aa-agenda-access-request-form');";
        echo "if(!f){return;}f.addEventListener('submit',function(){";
        echo "var b=f.querySelector('button[type=submit]');";
        echo "if(b){b.disabled=true;}});})();\n";
        echo "</script>\n";
    }

    /**
     * GET bridge: never consumes. Renders auto-POST transition HTML.
     */
    public static function handle_bridge(): void {
        if (isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) === 'POST'
        ) {
            // Mistaken POST to bridge action → treat as consume if token present.
            self::handle_consume();
            return;
        }

        $raw = '';
        if (isset($_GET['token'])) {
            $raw = wp_unslash((string) $_GET['token']);
        }

        $token = AA_Agenda_Access_Token_Format::sanitize(is_string($raw) ? $raw : '');
        if ($token === '') {
            self::redirect_error_and_exit();
        }

        self::send_transition_headers();
        echo self::render_transition_html($token); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside renderer.
        exit;
    }

    /**
     * POST consume: exactly one HMAC call via UseCase; then redirect.
     */
    public static function handle_consume(): void {
        $raw = '';
        if (isset($_POST['token'])) {
            $raw = wp_unslash((string) $_POST['token']);
        }

        $token = AA_Agenda_Access_Token_Format::sanitize(is_string($raw) ? $raw : '');
        if ($token === '') {
            self::redirect_error_and_exit();
        }

        $use_case = new ConsumeAgendaAccessTokenUseCase();
        $result   = $use_case->execute($token);

        $url = isset($result['redirect_url']) && is_string($result['redirect_url'])
            ? $result['redirect_url']
            : (new AA_Agenda_Access_Redirect_Policy())->error_login_url();

        // Never trust a non-allowlisted success URL; errors may point at login.
        $policy = new AA_Agenda_Access_Redirect_Policy();
        if (($result['status'] ?? '') === ConsumeAgendaAccessTokenUseCase::STATUS_SUCCESS
            || !empty($result['soft_success'])
        ) {
            if (!$policy->is_allowlisted_success_url($url)) {
                $url = $policy->calendar_url();
            }
        }

        nocache_headers();
        wp_safe_redirect($url);
        exit;
    }

    /**
     * POST request link: gate → nonce → transient/HMAC via UseCase → always PRG neutro.
     */
    public static function handle_request(): void {
        $policy = new AA_Agenda_Access_Redirect_Policy();
        $redirect_to = '';
        if (isset($_POST['redirect_to'])) {
            $redirect_to = wp_unslash((string) $_POST['redirect_to']);
        }
        $redirect_url = $policy->link_sent_login_url($redirect_to);

        // Gate before transient and before any HMAC. POST fields never alter classification.
        if (!AA_Agenda_Access_Request_Eligibility::can_request()) {
            self::redirect_link_sent_and_exit($redirect_url);
        }

        $nonce = '';
        if (isset($_POST[self::NONCE_FIELD])) {
            $nonce = wp_unslash((string) $_POST[self::NONCE_FIELD]);
        }
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            self::redirect_link_sent_and_exit($redirect_url);
        }

        $use_case = self::$request_use_case ?? new RequestAgendaAccessLinkUseCase();
        $use_case->execute();

        self::redirect_link_sent_and_exit($redirect_url);
    }

    private static function should_render_request_cta(): bool {
        $action = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Login screen routing only.
        if (isset($_GET['action'])) {
            $action = sanitize_key(wp_unslash((string) $_GET['action']));
        }
        if ($action !== '' && $action !== 'login') {
            return false;
        }

        return AA_Agenda_Access_Request_Eligibility::can_request();
    }

    /**
     * CTA HTML for login_message (no email / blog_id / identity fields).
     */
    public static function render_request_cta_html(): string {
        $policy = new AA_Agenda_Access_Redirect_Policy();
        $candidate = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect_to echo.
        if (isset($_REQUEST['redirect_to'])) {
            $candidate = wp_unslash((string) $_REQUEST['redirect_to']);
        }
        $redirect_to = $policy->sanitize_login_redirect($candidate);
        $action_url  = admin_url('admin-post.php');
        $nonce       = wp_create_nonce(self::NONCE_ACTION);

        $html  = '<div class="aa-agenda-access-request">';
        $html .= '<p class="aa-agenda-access-request-copy">'
            . esc_html__('Accede sin contraseña con un enlace a tu correo.', 'wp-agenda-automatizada')
            . '</p>';
        $html .= '<form method="post" action="' . esc_url($action_url) . '" id="aa-agenda-access-request-form">';
        $html .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_REQUEST) . '">';
        $html .= '<input type="hidden" name="' . esc_attr(self::NONCE_FIELD) . '" value="' . esc_attr($nonce) . '">';
        $html .= '<input type="hidden" name="redirect_to" value="' . esc_url($redirect_to) . '">';
        $html .= '<button type="submit" class="button button-primary aa-agenda-access-request-submit">'
            . esc_html__('Enviarme un enlace de acceso', 'wp-agenda-automatizada')
            . '</button>';
        $html .= '</form>';
        $html .= '<p class="aa-agenda-access-request-separator">'
            . esc_html__('O entra con usuario y contraseña', 'wp-agenda-automatizada')
            . '</p>';
        $html .= '</div>';

        return $html;
    }

    private static function redirect_link_sent_and_exit(string $url): void {
        nocache_headers();
        wp_safe_redirect($url);
        exit;
    }

    private static function redirect_error_and_exit(): void {
        $url = (new AA_Agenda_Access_Redirect_Policy())->error_login_url();
        nocache_headers();
        wp_safe_redirect($url);
        exit;
    }

    private static function send_transition_headers(): void {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('X-Frame-Options: DENY');
            header('Content-Security-Policy: frame-ancestors \'none\'');
        }
        nocache_headers();
    }

    /**
     * Autocontained transition page: auto-POST + manual fallback. No external assets.
     */
    public static function render_transition_html(string $token): string {
        $action_url = admin_url('admin-post.php');
        $clean_url  = admin_url('admin-post.php?action=' . self::ACTION_BRIDGE);
        $safe_token = esc_attr($token);
        $action_name = esc_attr(self::ACTION_CONSUME);

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Entrando a tu agenda…</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#0f172a}
main{text-align:center;padding:24px;max-width:28rem}
p{margin:0 0 16px;line-height:1.5}
button{appearance:none;border:0;border-radius:10px;background:#4f46e5;color:#fff;font-weight:600;font-size:14px;padding:12px 20px;cursor:pointer}
noscript p{margin-top:16px;color:#64748b;font-size:13px}
</style>
</head>
<body>
<main>
<p>Entrando a tu agenda…</p>
<form id="aa-agenda-access-form" method="post" action="' . esc_url($action_url) . '" referrerpolicy="no-referrer">
<input type="hidden" name="action" value="' . $action_name . '">
<input type="hidden" name="token" value="' . $safe_token . '">
<button type="submit">Entrar a mi agenda</button>
</form>
<noscript><p>JavaScript está desactivado. Pulsa el botón para continuar.</p></noscript>
</main>
<script>
(function(){
  try {
    if (window.history && typeof history.replaceState === "function") {
      history.replaceState(null, "", ' . wp_json_encode($clean_url) . ');
    }
  } catch (e) {}
  var f = document.getElementById("aa-agenda-access-form");
  if (f) { f.submit(); }
})();
</script>
</body>
</html>';
    }
}

<?php
/**
 * Public admin-post handlers for agenda magic-link bridge (GET) and consume (POST).
 *
 * C2 — managed_multisite_site tenants via HMAC. Never logs tokens.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-token-format.php';
require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-redirect-policy.php';
require_once dirname(__DIR__, 2) . '/application/auth/ConsumeAgendaAccessTokenUseCase.php';

final class AA_Agenda_Access_Handlers {

    public const ACTION_BRIDGE = 'aa_agenda_access';
    public const ACTION_CONSUME = 'aa_agenda_access_consume';

    public static function register(): void {
        add_action('admin_post_nopriv_' . self::ACTION_BRIDGE, [__CLASS__, 'handle_bridge']);
        add_action('admin_post_' . self::ACTION_BRIDGE, [__CLASS__, 'handle_bridge']);
        add_action('admin_post_nopriv_' . self::ACTION_CONSUME, [__CLASS__, 'handle_consume']);
        add_action('admin_post_' . self::ACTION_CONSUME, [__CLASS__, 'handle_consume']);
        add_filter('login_message', [__CLASS__, 'filter_login_error_message']);
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

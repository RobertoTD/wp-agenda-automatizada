<?php
/**
 * Harness standalone de `AA_AI_Chat_Error_Ux`.
 *
 *   php tests/application/ai/test-ai-chat-error-ux-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . http_build_query($args, '', '&');
    }
}

require_once __DIR__ . '/../../../includes/application/ai/AI_Chat_Error_Ux.php';
require_once __DIR__ . '/../../../includes/application/ai/AI_Setup_Action_Link_Builder.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' - ' . $detail) . "\n";
}

function msg_has_no_technical_terms(string $message): bool {
    $lower = strtolower($message);
    $forbidden = ['client secret', 'backend', 'installation_id', 'no_installation_id', 'token', 'endpoint'];
    foreach ($forbidden as $term) {
        if (strpos($lower, $term) !== false) {
            return false;
        }
    }
    return true;
}

$technical_secret = 'Falta el client secret del backend para conectar con Node.';
$technical_install = 'La agenda no está vinculada a una instalación (installation_id).';

// A: ai_backend_not_configured
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('ai_backend_not_configured', $technical_secret);
ac(
    'ai_backend_not_configured → copy cuenta DEOIA',
    $msg === AA_AI_Chat_Error_Ux::MSG_REQUIRES_ACCOUNT,
    $msg
);
ac(
    'ai_backend_not_configured sin términos técnicos',
    msg_has_no_technical_terms($msg),
    $msg
);
$actions = AA_AI_Chat_Error_Ux::actions_for_code('ai_backend_not_configured');
ac(
    'ai_backend_not_configured incluye CTA Vincular cuenta',
    count($actions) === 1
        && ($actions[0]['label'] ?? '') === 'Vincular cuenta'
        && strpos($actions[0]['url'] ?? '', 'setup_focus=google_calendar') !== false
        && strpos($actions[0]['url'] ?? '', '#aa-google-calendar-root') !== false,
    json_encode($actions, JSON_UNESCAPED_SLASHES)
);

// A: no_installation_id
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('no_installation_id', $technical_install);
ac(
    'no_installation_id → copy cuenta DEOIA',
    $msg === AA_AI_Chat_Error_Ux::MSG_REQUIRES_ACCOUNT,
    $msg
);
ac(
    'no_installation_id ignora provider_error técnico',
    strpos(strtolower($msg), 'installation') === false,
    $msg
);
$actions = AA_AI_Chat_Error_Ux::actions_for_code('no_installation_id');
ac(
    'no_installation_id incluye CTA Vincular cuenta',
    count($actions) === 1 && ($actions[0]['label'] ?? '') === 'Vincular cuenta',
    json_encode($actions, JSON_UNESCAPED_UNICODE)
);

// B: backend_disabled
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('backend_disabled', 'AI disabled on server');
ac(
    'backend_disabled → copy plan',
    $msg === AA_AI_Chat_Error_Ux::MSG_PLAN_DISABLED,
    $msg
);
$actions = AA_AI_Chat_Error_Ux::actions_for_code('backend_disabled');
ac(
    'backend_disabled sin CTA Google',
    $actions === [],
    json_encode($actions)
);

// C: quota_exceeded
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('quota_exceeded', 'quota raw');
ac(
    'quota_exceeded → copy cuota',
    $msg === AA_AI_Chat_Error_Ux::MSG_QUOTA_EXCEEDED,
    $msg
);

// D/E: generic / network
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('ai_unavailable', 'Connection refused');
ac(
    'ai_unavailable → copy conexión temporal',
    $msg === AA_AI_Chat_Error_Ux::MSG_CONNECTION_UNAVAILABLE,
    $msg
);
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('unknown_code', 'Internal stack trace');
ac(
    'código desconocido → copy conexión temporal',
    $msg === AA_AI_Chat_Error_Ux::MSG_CONNECTION_UNAVAILABLE,
    $msg
);
$msg = AA_AI_Chat_Error_Ux::user_message_for_code('ai_not_configured', 'OPENAI_KEY missing');
ac(
    'ai_not_configured → copy proveedor genérico',
    $msg === AA_AI_Chat_Error_Ux::MSG_PROVIDER_UNAVAILABLE,
    $msg
);

// Chat service integration: preserved provider codes
require_once __DIR__ . '/../../../includes/services/ai/contracts/interface-aa-llm-client.php';
require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-admin-ai-chat-service.php';

final class AA_Test_LLM_No_Installation_Client implements AA_LLM_Client_Interface {
    public function chat(array $payload) {
        return [
            'ok'    => false,
            'error' => 'La agenda no está vinculada a una instalación.',
            'code'  => 'no_installation_id',
            'raw'   => '{"ok":false,"code":"no_installation_id"}',
        ];
    }
}

$service = new AA_Admin_AI_Chat_Service(new AA_Test_LLM_No_Installation_Client());
$out     = $service->handle('hola', null);
ac(
    'chat service no_installation_id → mensaje humano A',
    empty($out['ok'])
        && ($out['code'] ?? '') === 'no_installation_id'
        && ($out['error'] ?? '') === AA_AI_Chat_Error_Ux::MSG_REQUIRES_ACCOUNT
        && strpos(strtolower((string) ($out['error'] ?? '')), 'installation') === false,
    json_encode($out, JSON_UNESCAPED_UNICODE)
);
ac(
    'chat service no_installation_id incluye actions',
    !empty($out['actions']) && ($out['actions'][0]['label'] ?? '') === 'Vincular cuenta',
    json_encode($out['actions'] ?? [], JSON_UNESCAPED_UNICODE)
);

echo "\n{$passed}/{$total} acceptance checks passed.\n";
exit($passed === $total ? 0 : 1);

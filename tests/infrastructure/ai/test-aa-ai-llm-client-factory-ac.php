<?php
/**
 * AC para AA_AI_LLM_Client_Factory y preservación de codes en chat service.
 *
 *   php tests/infrastructure/ai/test-aa-ai-llm-client-factory-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$GLOBALS['aa_test_options'] = [];

function get_option($key, $default = false) {
    if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
        return $GLOBALS['aa_test_options'][$key];
    }
    return $default;
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string) {
        return rtrim((string) $string, '/');
    }
}

if (!function_exists('aa_send_authenticated_request')) {
function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
    return [];
}
}

$root = dirname(__DIR__, 3);

require_once $root . '/includes/services/ai/contracts/interface-aa-llm-client.php';
require_once $root . '/includes/services/ai/providers/ollama/class-aa-ollama-client.php';
require_once $root . '/includes/services/ai/providers/backend/class-aa-backend-llm-client.php';
require_once $root . '/includes/infrastructure/ai/class-aa-ai-llm-client-factory.php';
require_once $root . '/includes/services/ai/chat/class-aa-admin-ai-chat-service.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' — ' . $detail) . "\n";
}

function reset_options(): void {
    $GLOBALS['aa_test_options'] = [];
}

// --- Factory: managed + secret → backend ---
reset_options();
$GLOBALS['aa_test_options'] = [
    'aa_client_secret'   => 'test-secret',
    'aa_backend_status'  => 'ready',
];
$res = AA_AI_LLM_Client_Factory::resolve();
ac(
    'managed tenant with secret resolves backend',
    !empty($res['ok'])
        && ($res['effective_mode'] ?? '') === 'backend'
        && $res['client'] instanceof AA_Backend_LLM_Client
        && ($res['meta']['fallback'] ?? true) === false,
    json_encode($res['meta'] ?? [])
);

// --- Factory: managed without secret → error, not local ---
reset_options();
$GLOBALS['aa_test_options'] = [
    'aa_backend_status' => 'ready',
    'aa_client_secret'  => '',
];
$res = AA_AI_LLM_Client_Factory::resolve();
ac(
    'managed tenant without secret fails with ai_backend_not_configured',
    empty($res['ok'])
        && ($res['code'] ?? '') === 'ai_backend_not_configured'
        && ($res['meta']['fallback'] ?? true) === false,
    json_encode($res)
);

// --- Factory: non-managed default → local ---
reset_options();
$res = AA_AI_LLM_Client_Factory::resolve();
ac(
    'non-managed default resolves local',
    !empty($res['ok'])
        && ($res['effective_mode'] ?? '') === 'local'
        && $res['client'] instanceof AA_Ollama_Client,
    json_encode($res['meta'] ?? [])
);

// --- Factory: explicit backend without secret via try_build (no constant pollution) ---
reset_options();
$backend = AA_AI_LLM_Client_Factory::try_build_backend_client();
ac(
    'try_build_backend_client without secret fails',
    empty($backend['ok']) && ($backend['code'] ?? '') === 'ai_backend_not_configured',
    json_encode($backend)
);

// --- Chat service: quota_exceeded preserved ---
final class AA_Test_LLM_Quota_Client implements AA_LLM_Client_Interface {
    public function chat(array $payload) {
        return [
            'ok'    => false,
            'error' => 'Has alcanzado el límite de consultas de IA para este período.',
            'code'  => 'quota_exceeded',
            'raw'   => '{"ok":false,"code":"quota_exceeded"}',
        ];
    }
}

$service = new AA_Admin_AI_Chat_Service(new AA_Test_LLM_Quota_Client());
$out     = $service->handle('crear cita mañana', null);
ac(
    'chat service preserves quota_exceeded code',
    empty($out['ok']) && ($out['code'] ?? '') === 'quota_exceeded',
    json_encode($out)
);

// --- Chat service: generic transport → ai_unavailable ---
final class AA_Test_LLM_Generic_Client implements AA_LLM_Client_Interface {
    public function chat(array $payload) {
        return [
            'ok'    => false,
            'error' => 'Connection refused',
            'raw'   => null,
        ];
    }
}

$service = new AA_Admin_AI_Chat_Service(new AA_Test_LLM_Generic_Client());
$out     = $service->handle('hola', null);
ac(
    'chat service generic provider error maps to ai_unavailable',
    empty($out['ok']) && ($out['code'] ?? '') === 'ai_unavailable',
    json_encode($out)
);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);

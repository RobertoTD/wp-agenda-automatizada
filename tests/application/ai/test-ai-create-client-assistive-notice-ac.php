<?php
/**
 * Harness standalone del aviso auxiliar create_client dentro de create_booking.
 *
 *   php tests/application/ai/test-ai-create-client-assistive-notice-ac.php
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Application\AI
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

require_once __DIR__ . '/../../../includes/services/ai/contracts/interface-aa-llm-client.php';
require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-admin-ai-chat-service.php';

final class AA_Test_LLM_Client_For_Assistive_Notice implements AA_LLM_Client_Interface {
    public function chat(array $payload) {
        return ['ok' => false, 'error' => 'not_used'];
    }
}

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

$service = new AA_Admin_AI_Chat_Service(new AA_Test_LLM_Client_For_Assistive_Notice());
$method = new ReflectionMethod($service, 'attach_create_client_assistive_notice');
$method->setAccessible(true);

$intent_result = [
    'intent'     => 'create_booking',
    'status'     => 'needs_resolution',
    'reply'      => 'Seguimos con tu cita. Me falta el servicio.',
    'resolution' => [
        'draft_state' => [
            'state' => 'collecting_required_fields',
            'draft' => ['client' => ['nombre' => 'Juan']],
        ],
        'reply_ui' => [
            'text'       => 'Seguimos con tu cita. Me falta el servicio.',
            'cta'        => 'collect_input',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => 'Juan',
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
        ],
    ],
];

$method->invokeArgs($service, [&$intent_result]);

$reply_ui = $intent_result['resolution']['reply_ui'];
$notice = isset($reply_ui['assistive_notice']) && is_array($reply_ui['assistive_notice'])
    ? $reply_ui['assistive_notice']
    : [];
$action = isset($notice['actions'][0]) && is_array($notice['actions'][0])
    ? $notice['actions'][0]
    : [];

ac('intent_result conserva create_booking', ($intent_result['intent'] ?? null) === 'create_booking');
ac('draft_state sigue presente', isset($intent_result['resolution']['draft_state']) && is_array($intent_result['resolution']['draft_state']));
ac('reply_ui conserva cta normal', ($reply_ui['cta'] ?? null) === 'collect_input', json_encode($reply_ui, JSON_UNESCAPED_UNICODE));
ac('assistive_notice text existe', isset($notice['text']) && strpos($notice['text'], 'Por ahora no puedo crear clientes') === 0);
ac('assistive_notice action label', ($action['label'] ?? null) === 'Ir a Clientes', json_encode($action, JSON_UNESCAPED_UNICODE));
ac('assistive_notice URL contiene module=clients', isset($action['url']) && strpos($action['url'], 'module=clients') !== false, $action['url'] ?? '');
ac('assistive_notice URL contiene setup_focus=clients', isset($action['url']) && strpos($action['url'], 'setup_focus=clients') !== false, $action['url'] ?? '');

echo "\n{$passed}/{$total} acceptance checks passed.\n";
exit($passed === $total ? 0 : 1);
